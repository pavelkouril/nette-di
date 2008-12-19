<?php

/**
 * Nette Framework
 *
 * Copyright (c) 2004, 2008 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://nettephp.com
 *
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @license    http://nettephp.com/license  Nette license
 * @link       http://nettephp.com
 * @category   Nette
 * @package    Nette\Config
 * @version    $Id$
 */

/*namespace Nette\Config;*/



require_once dirname(__FILE__) . '/../Config/IConfigAdapter.php';



/**
 * Reading and writing INI files.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @package    Nette\Config
 */
final class ConfigAdapterIni implements IConfigAdapter
{

	/** @var string  key nesting separator (key1> key2> key3) */
	static public $keySeparator = '.';

	/** @var string  section inheriting separator (section < parent) */
	static public $sectionSeparator = ' < ';



	/**
	 * Static class - cannot be instantiated.
	 */
	final public function __construct()
	{
		throw new /*\*/LogicException("Cannot instantiate static class " . get_class($this));
	}



	/**
	 * Reads configuration from INI file.
	 * @param  string  file name
	 * @param  string  section to load
	 * @return array
	 * @throws \InvalidStateException
	 */
	public static function load($file, $section = NULL)
	{
		if (!is_file($file) || !is_readable($file)) {
			throw new /*\*/FileNotFoundException("File '$file' is missing or is not readable.");
		}

		/*Nette\*/Tools::tryError();
		$data = parse_ini_file($file, TRUE);
		if (/*Nette\*/Tools::catchError($msg)) {
			throw new /*\*/Exception($msg);
		}

		// process extends sections like [staging < production]
		if (self::$sectionSeparator) {
			foreach ($data as $secName => $secData) {
				// ignore non-section data
				if (!is_array($secData)) continue;

				$separator = trim(self::$sectionSeparator);
				$parts = explode($separator, strtr($secName, ':', $separator)); // special support for separator ':'
				if (count($parts) > 1) {
					$child = trim($parts[0]);
					if ($child === '') {
						throw new /*\*/InvalidStateException("Invalid section [$secName] in '$file'.");
					}
					$parent = trim($parts[1]);
					if (isset($data[$parent])) {
						$secData += $data[$parent];
					} else {
						throw new /*\*/InvalidStateException("Missing parent section [$parent] in '$file'.");
					}
					$data[$child] = $secData;
					unset($data[$secName]);
				}
			}
		}

		if ($section !== NULL) {
			if (isset($data[$section])) {
				$data = array($section => $data[$section]);
			} else {
				throw new /*\*/InvalidStateException("There is not section [$section] in '$file'.");
			}
		}

		// process key separators (key1> key2> key3)
		if (self::$keySeparator) {
			$output = array();
			foreach ($data as $secName => $secData) {
				$cursorS = & $output;
				foreach (explode(self::$keySeparator, $secName) as $part) {
					$cursorS = & $cursorS[$part];
				}

				if (is_array($secData)) {
					foreach ($secData as $key => $val) {
						$cursor = & $cursorS;
						foreach (explode(self::$keySeparator, $key) as $part) {
							$cursor = & $cursor[$part];
						}
						$cursor = $val;
					}
				} else {
					// non-section data
					$cursorS = $secData;
				}
			}
			$data = $output;
		}

		if ($section !== NULL) {
			$data = $data[$section];
		}

		return $data;
	}



	/**
	 * Write INI file.
	 * @param  Config to save
	 * @param  string  file
	 * @param  string  section name
	 * @return void
	 */
	public static function save($config, $file, $section = NULL)
	{
		$output = array();
		$output[] = '; generated by Nette';// at ' . @strftime('%c');
		$output[] = '';

		if ($section === NULL) {
			foreach ($config as $secName => $secData) {
				if (!(is_array($secData) || $secData instanceof /*\*/Traversable)) {
					throw new /*\*/InvalidStateException("Invalid section '$section'.");
				}

				$output[] = "[$secName]";
				self::build($secData, $output, '');
				$output[] = '';
			}

		} else {
			$output[] = "[$section]";
			self::build($config, $output, '');
			$output[] = '';
		}

		if (!file_put_contents($file, implode(PHP_EOL, $output))) {
			throw new /*\*/IOException("Cannot write file '$file'.");
		}
	}



	/**
	 * Recursive builds INI list.
	 * @param  array|\Traversable
	 * @param  array
	 * @param  string
	 * @return void
	 */
	private static function build($input, & $output, $prefix)
	{
		foreach ($input as $key => $val) {
			if (is_array($val) || $val instanceof /*\*/Traversable) {
				self::build($val, $output, $prefix . $key . self::$keySeparator);

			} elseif (is_bool($val)) {
				$output[] = "$prefix$key = " . ($val ? 'true' : 'false');

			} elseif (is_numeric($val)) {
				$output[] = "$prefix$key = $val";

			} elseif (is_string($val)) {
				$output[] = "$prefix$key = \"$val\"";

			} else {
				throw new /*\*/InvalidArgumentException("The '$prefix$key' item must be scalar or array.");
			}
		}
	}

}