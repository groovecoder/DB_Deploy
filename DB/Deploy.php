<?php
/**
 * DB_Deploy change-management tool
 *
 * @category DB
 * @package  DB_Deploy
 * @author   Luke Crouch <luke.crouch@gmail.com>
 * @license  http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 * @link     ?
 */

require_once 'DB/Deploy/Syntax/Factory.php';

/**
 *  Generate SQL script for db using dbdeploy schema version table and delta scripts
 *
 * @category DB
 * @package  DB_Deploy
 * @author   Luke Crouch <luke.crouch@gmail.com>
 * @license  http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 * @link     ?
 */
class DB_Deploy
{
    
    public static $TABLE_NAME = 'changelog';
    
    /**
     * PDO connection URL for database
     *
     * @var string
     */
    protected $url = null;
    
    /**
     * database userid for accessing changelog table
     *
     * @var string
     */
    protected $userid = 'dbdeploy';
    
    /**
     * database password for accessing changelog table
     *
     * @var string
     */
    protected $password = 'dbdeploy';
    
    /**
     * path to the .ini config file
     *
     * @var string
     */
    protected $configFile = 'dbdeploy/dbdeploy.ini';
    
    /**
     * path to the directory containing dbdeploy delta script files
     *
     * @var string
     */
    protected $dir = 'dbdeploy/deltas';
    
    /**
     * path for the output file to which the deployment SQL will be saved
     *
     * @var string
     */
    protected $outputFile = 'dbdeploy/dbdeploy_deploy.sql';
    
    /**
     * path for the output file to which the undo SQL will be saved
     *
     * @var string
     */
    protected $undoOutputFile = 'dbdeploy/dbdeploy_undo.sql';
    
    /**
     * property for changeset "groups" (under-utilized feature)
     *
     * @var string
     */
    protected $deltaSet = 'Main';
    
    /**
     * highest-numbered delta file to execute
     *
     * @var integer
     */
    protected $lastChangeToApply = 999;
    
    /**
     * DbmsSyntax object for generating Dbms-specific SQL
     *
     * @var DbmsSyntax
     */
    protected $dbmsSyntax = null;
    
    /**
     * fire method called by command-line runner
     * 
     * Parses ini configuration file and executes main method.
     * 
     * @param array $args command-line arguments
     * 
     * @return null
     */
    public function fire($args = null)
    {
        // cycle through given args
        for ( $i = 0, $argcount = count($args); $i < $argcount; $i ++ ) {
            $arg = $args [ $i ];
            if ($arg == "-configfile" || $arg == "-c") {
                if (! isset($args [ $i + 1 ])) {
                    echo "You must specify a config file when using the -configfile argument.\n";
                } else {
                    echo "setting configFile to: " . $args [ $i + 1 ] . "\n";
                    $this->configFile = $args [ $i + 1 ];
                }
            }
        }
        
        $iniArray  = parse_ini_file($this->configFile, true);
        $this->url = $iniArray [ 'dbdeploy' ] [ 'url' ];
        
        // override remaining properties from config file, if supplied
        if ($iniArray [ 'dbdeploy' ] [ 'userid' ])
            $this->userid = $iniArray [ 'dbdeploy' ] [ 'userid' ];
        if ($iniArray [ 'dbdeploy' ] [ 'password' ])
            $this->userid = $iniArray [ 'dbdeploy' ] [ 'password' ];
        if ($iniArray['dbdeploy']['outputFile'])
        	$this->outputFile = $iniArray['dbdeploy']['outputFile'];
        if ($iniArray['dbdeploy']['undoOutputFile'])
        	$this->undoOutputFile = $iniArray['dbdeploy']['undoOutputFile'];
        if ($iniArray['deltaDir']['deltaDir'])
        	$this->dir = $iniArray['deltaDir']['deltaDir'];
        
        $this->main();
    }
    
    /**
     * Main method
     * 
     * Instantiates DbmsSyntax object, opens file handles for output, connects to DB to check
     * for DB's current version, generates and aggregates appropriate SQL to deploy and roll back
     * the necessary DB changes.
     *
     * @return null
     */
    function main()
    {
        try {
            // get correct DbmsSyntax object
            $dbms              = substr($this->url, 0, strpos($this->url, ':'));
            $dbmsSyntaxFactory = new DB_Deploy_Syntax_Factory($dbms);
            $this->dbmsSyntax  = $dbmsSyntaxFactory->getDbmsSyntax();
            
            // open file handles for output
            $outputFileHandle     = fopen($this->outputFile, "w+");
            $undoOutputFileHandle = fopen($this->undoOutputFile, "w+");
            
            // figure out which revisions are in the db already
            $this->appliedChangeNumbers = $this->getAppliedChangeNumbers();
            echo "Current db revision: " . $this->getLastChangeAppliedInDb() . "\n";
            
            // generate sql file needed to take db to "lastChangeToApply" version
            echo "Creating deploy SQL file, " . $this->outputFile . "\n";
            $doSql   = $this->doDeploy();
            echo "Creating undo SQL file, " . $this->undoOutputFile . "\n";
            $undoSql = $this->undoDeploy();
            
            // write the do and undo SQL to their respective files
            fwrite($outputFileHandle, $doSql);
            fwrite($undoOutputFileHandle, $undoSql);
        
        } catch ( Exception $e ) {
            throw new Exception($e);
        }
    }
    
    /**
     * Method to check db for applied DB revisions.
     * 
     * Connects to database and selects all records from changelog table, order by change number.
     * Stores the result in a local property.
     *
     * @return unknown
     */
    function getAppliedChangeNumbers()
    {
        if (count($this->appliedChangeNumbers) == 0) {
            echo "Getting applied changed numbers from DB at: " . $this->url . "\n";
            
            $appliedChangeNumbers = array ( );
            $dbh                  = new PDO($this->url, $this->userid, $this->password);
            
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = "SELECT * FROM " . DB_Deploy::$TABLE_NAME . " WHERE delta_set = '$this->deltaSet' ORDER BY change_number";
            foreach ( $dbh->query($sql) as $change ) {
                $appliedChangeNumbers [] = $change [ 'change_number' ];
            }
            $this->appliedChangeNumbers = $appliedChangeNumbers;
        }
        return $this->appliedChangeNumbers;
    }
    
    /**
     * Method to get highest-numbered revision applied in DB.
     *
     * @return unknown
     */
    function getLastChangeAppliedInDb()
    {
        return (count($this->appliedChangeNumbers) > 0) ? max($this->appliedChangeNumbers) : 0;
    }
    
    /**
     * Primary method to generate and aggregate deployment SQL from delta scripts.
     * 
     * This method loops over all files in the deltas directory, aggregates the delta scripts SQL
     * and changelog management SQL.
     *
     * @return string All SQL necessary for deployment.
     */
    function doDeploy()
    {
        $sqlToPerformDeploy    = '';
        $lastChangeAppliedInDb = $this->getLastChangeAppliedInDb();
        $files                 = $this->getDeltasFilesArray();
        
        ksort($files);
        foreach ( $files as $fileChangeNumber => $fileName ) {
            if ($fileChangeNumber > $lastChangeAppliedInDb && $fileChangeNumber <= $this->lastChangeToApply) {
            	echo "\tadding deploy SQL from " . $fileName . " ... ";

                $sqlToPerformDeploy .= '--------------- Fragment begins: ' . $fileChangeNumber . ' ---------------' . "\n";
                $sqlToPerformDeploy .= 'INSERT INTO ' . DB_Deploy::$TABLE_NAME . ' (change_number, delta_set, start_dt, applied_by, description)' . ' VALUES (' . $fileChangeNumber . ', \'' . $this->deltaSet . '\', ' . $this->dbmsSyntax->generateTimestamp() . ', \'dbdeploy\', \'' . $fileName . '\');' . "\n";
                
                $fullFileName = $this->dir . '/' . $fileName;
                $fh           = fopen($fullFileName, 'r');
                $contents     = fread($fh, filesize($fullFileName));
                
                $deploySQLFromFile   = substr($contents, 0, strpos($contents, '--//@UNDO'));
                $sqlToPerformDeploy .= $deploySQLFromFile;
                $sqlToPerformDeploy .= 'UPDATE ' . DB_Deploy::$TABLE_NAME . ' SET complete_dt = ' . $this->dbmsSyntax->generateTimestamp() . ' WHERE change_number = ' . $fileChangeNumber . ' AND delta_set = \'' . $this->deltaSet . '\';' . "\n";
                $sqlToPerformDeploy .= '--------------- Fragment ends: ' . $fileChangeNumber . ' ---------------' . "\n";
                echo "done.\n";
            }
        }
        return $sqlToPerformDeploy;
    }
    
    /**
     * Primary method to generate and aggregate rollback SQL from delta scripts.
     * 
     * This method loops over all files in the deltas directory, aggregates the delta scripts SQL
     * and changelog management SQL.
     *
     * @return string All SQL necessary to undo deployment.
     */
    function undoDeploy()
    {
        $sqlToPerformUndo      = '';
        $lastChangeAppliedInDb = $this->getLastChangeAppliedInDb();
        $files                 = $this->getDeltasFilesArray();
        
        krsort($files);
        foreach ( $files as $fileChangeNumber => $fileName ) {
            if ($fileChangeNumber > $lastChangeAppliedInDb && $fileChangeNumber <= $this->lastChangeToApply) {
            	
            	echo "\tadding undo SQL from " . $fileName . " ... ";

                $fullFileName = $this->dir . '/' . $fileName;
                $fh           = fopen($fullFileName, 'r');
                $contents     = fread($fh, filesize($fullFileName));
                
                $undoSQLFromFile = substr($contents, strpos($contents, '--//@UNDO') + 9);
                
                $sqlToPerformUndo .= $undoSQLFromFile;
                $sqlToPerformUndo .= 'DELETE FROM ' . DB_Deploy::$TABLE_NAME . ' WHERE change_number = ' . $fileChangeNumber . ' AND delta_set = \'' . $this->deltaSet . '\';' . "\n";
                $sqlToPerformUndo .= '--------------- Fragment ends: ' . $fileChangeNumber . ' ---------------' . "\n";
                echo "done.\n";
                
            }
        }
        return $sqlToPerformUndo;
    }
    
    /**
     * get the files for aggregation
     *
     * @return array delta script file-names
     */
    function getDeltasFilesArray()
    {
        $baseDir                = realpath($this->dir);
        $dh                     = opendir($baseDir);
        $fileChangeNumberPrefix = '';
        
        while ( ($file = readdir($dh)) !== false ) {
            if (preg_match('[\d+]', $file, $fileChangeNumberPrefix)) {
                $files [ $fileChangeNumberPrefix [ 0 ] ] = $file;
            }
        }
        return $files;
    }
    
    /**
     * Set the config file path
     * 
     * @return null
     */
    function setConfigFile($configFile)
    {
    	$this->configFile = $configFile;
    }
}

?>