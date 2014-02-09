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

require_once 'DB/Deploy/Syntax.php';

/**
 *  Factory for generating dbms-specific syntax-generating objects
 *
 * @category DB
 * @package  DB_Deploy
 * @author   Luke Crouch <luke.crouch@gmail.com>
 * @license  http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 * @link     ?
 */
class DB_Deploy_Syntax_Factory
{
    /**
     * dbms should match the the PDO DSN prefix of the driver to use
     *
     * @var string
     */
    private $dbms;
    
    /**
     * construct the factory, supplying the dbms of the syntax object to produce
     *
     * @param string $dbms the dbms identifier (derived from connection DSN)
     */
    public function __construct($dbms)
    {
        $this->dbms = $dbms;
    }
    
    /**
     * get the appropriate DbmsSyntax object based on the dbms value
     *
     * @return DbmsSyntax
     */
    public function getDbmsSyntax()
    {
        switch ( $this->dbms) {
        case ('sqlite') :
            include_once 'SQLITE.php';
            return new DB_Deploy_Syntax_SQLITE();
        case ('mysql') :
            include_once 'MYSQL.php';
            return new DB_Deploy_Syntax_MYSQL();
        case ('mssql') :
            include_once 'MSSQL.php';
            return new DB_Deploy_Syntax_MSSQL();
        case ('oci') :
        	include_once 'ORACLE.php';
        	return new DB_Deploy_Syntax_ORACLE();
        case ('pgsql') :
        	include_once 'PGSQL.php';
        	return new DB_Deploy_Syntax_PGSQL();
        default :
            throw new Exception($this->dbms . ' is not supported by dbdeploy task.');
        }
    }
}

?>