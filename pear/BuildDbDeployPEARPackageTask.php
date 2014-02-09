<?php
/*
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL.
 */

require_once 'phing/tasks/system/MatchingTask.php';
include_once 'phing/types/FileSet.php';
include_once 'phing/tasks/ext/pearpackage/Fileset.php';

/**
 *
 * @author   Luke Crouch <luke.crouch@gmail.com>
 * @package  phing.tasks.ext
 */
class BuildDbDeployPEARPackageTask extends MatchingTask {

    /** Base directory for reading files. */
    private $dir;

	private $version;
	private $state = 'beta';
	private $notes;
	
	private $filesets = array();
	
    /** Package file */
    private $packageFile;

    public function init() {
        include_once 'PEAR/PackageFileManager2.php';
        if (!class_exists('PEAR_PackageFileManager2')) {
            throw new BuildException("You must have installed PEAR_PackageFileManager2 (PEAR_PackageFileManager >= 1.6.0) in order to create a PEAR package.xml file.");
        }
    }

    private function setOptions($pkg){

		$options['baseinstalldir'] = '/';
        $options['packagedirectory'] = $this->dir->getAbsolutePath();

        if (empty($this->filesets)) {
			throw new BuildException("You must use a <fileset> tag to specify the files to include in the package.xml");
		}

		$options['filelistgenerator'] = 'Fileset';

		// Some PHING-specific options needed by our Fileset reader
		$options['phing_project'] = $this->getProject();
		$options['phing_filesets'] = $this->filesets;
		
		if ($this->packageFile !== null) {
            // create one w/ full path
            $f = new PhingFile($this->packageFile->getAbsolutePath());
            $options['packagefile'] = $f->getName();
            // must end in trailing slash
            $options['outputdirectory'] = $f->getParent() . DIRECTORY_SEPARATOR;
            $this->log("Creating package file: " . $f->getPath(), PROJECT_MSG_INFO);
        } else {
            $this->log("Creating [default] package.xml file in base directory.", PROJECT_MSG_INFO);
        }
		
		// add install exceptions
		$options['installexceptions'] = array(	'bin/dbdeploy.php' => '/',
												'bin/pear-dbdeploy' => '/',
												'bin/pear-dbdeploy.bat' => '/',
												);

		$options['dir_roles'] = array(	'dbdeploy_guide' => 'docs',
										'etc' => 'data',
										'example' => 'docs');

		$options['exceptions'] = array(	'bin/pear-dbdeploy.bat' => 'script',
										'bin/pear-dbdeploy' => 'script',
										'CREDITS' => 'docs',
										'CHANGELOG' => 'docs',
										'README' => 'docs',
										'TODO' => 'docs');

		$pkg->setOptions($options);

    }

    /**
     * Main entry point.
     * @return void
     */
    public function main() {

        if ($this->dir === null) {
            throw new BuildException("You must specify the \"dir\" attribute for PEAR package task.");
        }

		if ($this->version === null) {
            throw new BuildException("You must specify the \"version\" attribute for PEAR package task.");
        }

		$package = new PEAR_PackageFileManager2();

		$this->setOptions($package);

		// the hard-coded stuff
		$package->setPackage('DB_Deploy');
		$package->setSummary('dbdeploy is a Database Change Management tool.');
		$package->setDescription('dbdeploy is a Database Change Management tool. It is for developers or DBAs who want to evolve their database design - or refactor their database - in a simple, controlled, flexible and frequent manner. This PEAR package provides a php-driven command-line tool for using conventional dbdeploy script files.');
		$package->setChannel('pear.php.net');
		$package->setPackageType('php');

		$package->setReleaseVersion($this->version);
		$package->setAPIVersion($this->version);
		
		$package->setReleaseStability($this->state);
		$package->setAPIStability($this->state);
		
		$package->setNotes($this->notes);
		
		$package->setLicense('LGPL', 'http://www.gnu.org/licenses/lgpl.html');
		
		// Add package maintainers
		$package->addMaintainer('lead', 'groovecoder', 'Luke Crouch', 'luke.crouch@gmail.com');
		
		
		
		// (wow ... this is a poor design ...)
		//
		// note that the order of the method calls below is creating
		// sub-"release" sections which have specific rules.  This replaces
		// the platformexceptions system in the older version of PEAR's package.xml
		//
		// Programmatically, I feel the need to re-iterate that this API for PEAR_PackageFileManager
		// seems really wrong.  Sub-sections should be encapsulated in objects instead of having
		// a "flat" API that does not represent the structure being created....
		
		
		// creating a sub-section for 'windows'
			$package->addRelease();
			$package->setOSInstallCondition('windows');
			$package->addInstallAs('bin/dbdeploy.php', 'dbdeploy.php');
			$package->addInstallAs('bin/pear-dbdeploy.bat', 'pear-dbdeploy.bat');
			$package->addIgnoreToRelease('bin/pear-dbdeploy');
		
		// creating a sub-section for non-windows
			$package->addRelease();
			//$package->setOSInstallCondition('(*ix|*ux|darwin*|*BSD|SunOS*)');
			$package->addInstallAs('bin/dbdeploy.php', 'dbdeploy.php');
			$package->addInstallAs('bin/pear-dbdeploy', 'dbdeploy');
			$package->addIgnoreToRelease('bin/pear-dbdeploy.bat');
		

		// "core" dependencies
		$package->setPhpDep('5.0.0');
		$package->setPearinstallerDep('1.4.0');
		
		// now add the replacements ....
		$package->addReplacement('bin/pear-dbdeploy.bat', 'pear-config', '@PHP-BIN@', 'php_bin');
		$package->addReplacement('bin/pear-dbdeploy.bat', 'pear-config', '@BIN-DIR@', 'bin_dir');
		$package->addReplacement('bin/pear-dbdeploy.bat', 'pear-config', '@PEAR-DIR@', 'php_dir');
		$package->addReplacement('bin/pear-dbdeploy', 'pear-config', '@PHP-BIN@', 'php_bin');
		$package->addReplacement('bin/pear-dbdeploy', 'pear-config', '@BIN-DIR@', 'bin_dir');
		$package->addReplacement('bin/pear-dbdeploy', 'pear-config', '@PEAR-DIR@', 'php_dir');
		
		// now we run this weird generateContents() method that apparently 
		// is necessary before we can add replacements ... ?
		$package->generateContents();
		
        $e = $package->writePackageFile();

        if (PEAR::isError($e)) {
            throw new BuildException("Unable to write package file.", new Exception($e->getMessage()));
        }

    }

    /**
     * Used by the PEAR_PackageFileManager_PhingFileSet lister.
     * @return array FileSet[]
     */
    public function getFileSets() {
        return $this->filesets;
    }

    // -------------------------------
    // Set properties from XML
    // -------------------------------

    /**
     * Nested creator, creates a FileSet for this task
     *
     * @return FileSet The created fileset object
     */
    function createFileSet() {
        $num = array_push($this->filesets, new FileSet());
        return $this->filesets[$num-1];
    }

	/**
     * Set the version we are building.
     * @param string $v
     * @return void
     */
	public function setVersion($v){
		$this->version = $v;
	}

	/**
     * Set the state we are building.
     * @param string $v
     * @return void
     */
	public function setState($v) {
		$this->state = $v;
	}
	
	/**
	 * Sets release notes field.
	 * @param string $v
	 * @return void
	 */
	public function setNotes($v) {
		$this->notes = $v;
	}
    /**
     * Sets "dir" property from XML.
     * @param PhingFile $f
     * @return void
     */
    public function setDir(PhingFile $f) {
        $this->dir = $f;
    }

    /**
     * Sets the file to use for generated package.xml
     */
    public function setDestFile(PhingFile $f) {
        $this->packageFile = $f;
    }

}


