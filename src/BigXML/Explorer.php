<?php

namespace BigXML;

interface Explorer{

	/**
	 * @return int[]
	 */
	public function getIndexRoute();

	/**
	 * @return File|null
	 */
	public function getFile();

	/**
	 * @return string|null
	 */
	public function path();

}