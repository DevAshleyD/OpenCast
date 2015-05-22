<?php
require_once('./Services/ActiveRecord/class.ActiveRecord.php');

/**
 * Class xoctGroup
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class xoctGroup extends ActiveRecord {

	/**
	 * @return string
	 * @description Return the Name of your Database Table
	 * @deprecated
	 */
	static function returnDbTableName() {
		return 'xoct_group';
	}


	/**
	 * @param $obj_id
	 *
	 * @return xoctGroup[]
	 */
	public static function getAllForObjId($obj_id) {

		return self::where(array( 'serie_id' => $obj_id ))->orderBy('title')->get();
	}


	/**
	 * @var int
	 *
	 * @con_is_primary true
	 * @con_is_unique  true
	 * @con_has_field  true
	 * @con_fieldtype  integer
	 * @con_length     8
	 * @con_sequence   true
	 */
	protected $id = 0;
	/**
	 * @var int
	 * @con_has_field  true
	 * @con_length     8
	 * @con_fieldtype  integer
	 */
	protected $serie_id;
	/**
	 * @var string
	 * @con_has_field  true
	 * @con_length     1024
	 * @con_fieldtype  text
	 */
	protected $title;
	/**
	 * @var string
	 * @con_has_field  true
	 * @con_length     4000
	 * @con_fieldtype  text
	 */
	protected $description;
	/**
	 * @var int
	 * @con_has_field  true
	 * @con_length     1
	 * @con_fieldtype  integer
	 */
	protected $status;


	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}


	/**
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}


	/**
	 * @return int
	 */
	public function getSerieId() {
		return $this->serie_id;
	}


	/**
	 * @param int $serie_id
	 */
	public function setSerieId($serie_id) {
		$this->serie_id = $serie_id;
	}


	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}


	/**
	 * @param string $title
	 */
	public function setTitle($title) {
		$this->title = $title;
	}


	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}


	/**
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}


	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}


	/**
	 * @param int $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}
}