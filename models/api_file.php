<?php
/* SVN FILE: $Id$ */
/**
 * Api File Model 
 * 
 * For interacting with the Filesystem specified by ApiGenerator.filePath
 *
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright 2006-2008, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright       Copyright 2006-2008, Cake Software Foundation, Inc.
 * @link            http://www.cakefoundation.org/projects/info/cakephp CakePHP Project
 * @package         cake
 * @subpackage      cake.cake.libs.
 * @since           CakePHP v 1.2.0.4487
 * @version         
 * @modifiedby      
 * @lastmodified    
 * @license         http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('Vendor', 'ApiGenerator.Introspector');

class ApiFile extends Object {
/**
 * Name
 *
 * @var string
 */
	public $name = 'ApiFile';
/**
 * A list of folders to ignore.
 *
 * @var array
 **/
	public $ignoreFolders = array('config', 'webroot', 'tmp', 'locale');
/**
 * A list of files to ignore.
 *
 * @var array
 **/
	public $ignoreFiles = array('index.php', 'empty');
/**
 * a list of extensions to scan for
 *
 * @var array
 **/
	public $allowedExtensions = array('php');
/**
 * A regexp for file names. (will be made case insenstive)
 *
 * @var string
 **/
	public $fileRegExp = '[a-z_\-0-9]+';
/**
 * Folder instance
 *
 * @var Folder
 **/
	protected $_Folder;
/**
 * Current Extractor instance
 *
 * @var object
 **/
	protected $_extractor;
/**
 * storage for defined classes
 *
 * @var array
 **/
	protected $_definedClasses = array();
/**
 * storage for defined functions
 *
 * @var array
 **/
	protected $_definedFunctions = array();
/**
 * Constructor
 *
 * @return void
 **/
	public function __construct() {
		parent::__construct();
		$this->_Folder = new Folder(Configure::read('ApiGenerator.filePath'));
	}
/**
 * Read a path and return files and folders not in the excluded Folder list
 *
 * @param string $path The absolute path you wish to read.
 * @return array
 **/
	public function read($path) {
		$this->_Folder->cd($path);
		$ignore = $this->ignoreFiles;
		$ignore[] = '.';
		$contents = $this->_Folder->read(true, $ignore);
		$this->_filterFolders($contents[0], false);
		$this->_filterFiles($contents[1]);
		return $contents;
	}
/**
 * Recursive Read a path and return files and folders not in the excluded Folder list
 *
 * @param string $path The path you wish to read.
 * @return array
 **/
	public function fileList($path) {
		$this->_Folder->cd($path);
		$filePattern =  $this->fileRegExp . '\.' . implode('|', $this->allowedExtensions);
		$contents = $this->_Folder->findRecursive($filePattern);
		$this->_filterFolders($contents);
		$this->_filterFiles($contents);
		return $contents;
	}
/**
 * _filterFiles
 * 
 * Filter a file list and remove ignoreFolders
 * 
 * @param array $files List of files to filter and ignore. (reference)
 * @return void
 **/
	protected function _filterFolders(&$fileList, $recursiveList = true) {
		$count = count($fileList);
		foreach ($this->ignoreFolders as $blackListed) {
			if ($recursiveList) {
				$blackListed = DS . $blackListed . DS;
			}
			for ($i = 0; $i < $count; $i++) {
				if (isset($fileList[$i]) && strpos($fileList[$i], $blackListed) !== false) {
					unset($fileList[$i]);
				}
			}
		}
		$fileList = array_values($fileList);
	}
/**
 * remove files that don't match the allowedExtensions
 * or are on the ignoreFiles list
 *
 * @return void
 **/
	protected function _filterFiles(&$fileList) {
		$fileCount = count($fileList);
		foreach ($this->ignoreFiles as $ignored) {
			for ($i = 0; $i < $fileCount; $i++) {
				if ($ignored == $fileList[$i]) {
					unset($fileList[$i]);
				}
			}
		}
		foreach ($this->allowedExtensions as $ext) {
			$extPattern = '/\.' . $ext . '$/i';
			foreach ($fileList as $i => $file) {
				if (!preg_match($extPattern, $file)) {
					unset($fileList[$i]);
				}
			}
		}
		$fileList = array_values($fileList);
	}
/**
 * Loads the documentation extractor for a given classname.
 *
 * @param string $name Name of class to load. 
 * @access public
 * @return void
 */
	public function loadExtractor($type, $name) {
		$this->_extractor = Introspector::getReflector($type, $name);
	}
/**
 * Get the documentor extractor instance
 * 
 * @access public
 * @return object
 */	
	public function getExtractor() {
		return $this->_extractor;
	}
/**
 * Gets the parsed docs from the Extractor
 *
 * @return array Array with all the extracted docs.
 **/
	public function getDocs() {
		$this->_extractor->getAll();
		return $this->_extractor;
	}
/**
 * Load A File and extract docs for all classes contained in that file
 *
 * @param string $fullPath FullPath of the file you want to load.
 * @return array Array of all the docs from all the classes that were loaded as a result of the file being loaded.
 **/
	public function loadFile($filePath) {
		$baseClass = array();
		if (strpos($filePath, 'models') !== false) {
			$baseClass['Model'] = 'AppModel';
		}
		if (strpos($filePath, 'helpers') !== false) {
			$baseClass['Helper'] = 'AppHelper';
		}
		if (strpos($filePath, 'view') !== false) {
			$baseClass['View'] = 'View';
		}
		$this->_importBaseClasses($baseClass);
		$this->_getDefinedObjects();
		$newObjects = $this->findObjectsInFile($filePath);

		$docs = array();
		foreach ($newObjects as $type => $objects) {
			foreach ($objects as $element) {
				$this->loadExtractor($type, $element);
				if ($this->getExtractor()->getFileName() == $filePath) {
					$docs[$type][$element] = $this->getDocs();
				}
			}
		}
		return $docs;
	}
/**
 * gets the currently defined functions and classes 
 * so comparisons to new files can be made
 *
 * @return void
 **/
	protected function _getDefinedObjects() {
		$this->_definedClasses = get_declared_classes();
		$funcs = get_defined_functions();
		$this->_definedFunctions = $funcs['user'];
	}
/**
 * _findObjectsInFile
 * 
 * Fetches the class names and functions contained in the target file.
 *
 * @param string $filePath Absolute file path to file you want to read.
 * @param boolean $forceParse Force the manual read of a file.
 * @return array
 **/
	public function findObjectsInFile($filePath, $forceParse = false) {
		$new = array();
		$includedFiles = get_included_files();
		if (in_array($filePath, $includedFiles) || $forceParse) {
			$new['class'] = $this->_parseClassNamesInFile($filePath);
			$new['function'] = $this->_parseFunctionNamesInFile($filePath);
		} else {
			ob_start();
			include_once $filePath;
			ob_clean();
			$new['class'] = array_diff(get_declared_classes(), $this->_definedClasses);
			$funcs = get_defined_functions();
			$new['function'] = array_diff($funcs['user'], $this->_definedFunctions);
		}
		return $new;
	}
/**
 * Retrieves the classNames defined in a file.
 * Solves issues of reading docs from files that have already been included.
 *
 * @return array Array of class names that exist in the file.
 **/
	protected function _parseClassNamesInFile($fileName) {
		$foundClasses = array();
		$fileContent = file_get_contents($fileName);
		preg_match_all('/^\s*class\s([^\s]*)[^\{]+/mi', $fileContent, $matches, PREG_SET_ORDER);
		foreach ($matches as $className) {
			$foundClasses[] = $className[1];
		}
		return $foundClasses;
	}
/**
 * Retrieves global function names defined in a file.
 * Unlike the class parser which can cheat with regex.
 * Functions are a bit trickier.
 *
 * @return array
 **/
	protected function _parseFunctionNamesInFile($fileName) {
		$foundFuncs = array();
		$fileContent = file_get_contents($fileName);
		$funcNames = implode('|', $this->_definedFunctions);
		preg_match_all('/^\s*function\s*(' . $funcNames . ')[\s|\(]+/mi', $fileContent, $matches, PREG_SET_ORDER);
		foreach ($matches as $function) {
			$foundFuncs[] = $function[1];
		}
		return $foundFuncs;
	}
/**
 * Attempts to solve class dependancies by importing base CakePHP classes
 *
 * @return void
 **/
	protected function _importBaseClasses($classes = array()) {
		App::import('Core', array('Model', 'Helper', 'View'));
		foreach ($classes as $type => $class) {
			App::import($type, $class);
		}
	}
}
?>