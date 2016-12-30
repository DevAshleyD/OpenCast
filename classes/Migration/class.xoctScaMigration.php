<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * Class xoctScaMigration
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class xoctScaMigration {

	const EVENTS = 'events';
	const SERIES = 'series';
	const EVENT_ID_OLD = 'ext_id';
	const EVENT_ID_NEW = 'cast2_event_id';
	const SERIES_ID_OLD = 'channel_ext_id';
	const SERIES_ID_NEW = 'cast2_series_id';


	/**
	 * @var array
	 */
	protected $id_mapping = array(
		"series" => array(),
		"events" => array()
	);
	/**
	 * @var null
	 */
	protected $migration_data;
	/**
	 * @var xoctMigrationLog
	 */
	protected $log;
	/**
	 * @var ilDB|ilDBInnoDB|ilDBMySQL|ilDBOracle|ilDBPostgreSQL
	 */
	protected $db;


	/**
	 * xoctScaMigration constructor.
	 */
	public function __construct($migration_data = null) {
		global $ilDB;
		$this->migration_data = $migration_data;
		$this->log = xoctMigrationLog::getInstance();
		$this->db = $ilDB;
	}


	public static function initAndRun() {
		require_once(dirname(__FILE__) . '/../class.xoct.php');
		xoct::initILIAS();
		self::doInclude();
		$migration = new self();
		try {
			$migration->run();
		} catch (ilException $e) {
			xoctMigrationLog::getInstance()->write($e->getMessage());
			xoctMigrationLog::getInstance()->write('***Migration failed***');
		}
	}

	public static function doInclude() {
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/Migration/class.xoctMigrationLog.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/class.xoct.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/Scast/classes/class.ilObjScast.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/class.ilObjOpenCast.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/Series/class.xoctOpenCast.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/Scast/classes/Group/class.xscaGroup.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/IVTGroup/class.xoctIVTGroup.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/IVTGroup/class.xoctIVTGroupParticipant.php';
		require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/Invitations/class.xoctInvitation.php';
	}

	public function run() {
		$this->log->write('***Migration start***');
		if ($this->migration_data) {
			$this->createMapping($this->migration_data);
		} else {
			throw new ilException('Migration failed: no migration data given');
		}

		$this->migrateObjectData();
		$this->migrateInvitations();
		$this->log->write('***Migration Succeeded***');
	}

	protected function createMapping($migration_data) {
		if (!is_array($migration_data)) {
			$mapping = json_decode($migration_data, true);
			if (!is_array($mapping)) {
				throw new ilException('Mapping of ids failed: Format of migration data invalid');
			}
		}

		if (!$clips = $migration_data['clips']) {
			throw new ilException('Mapping of ids failed: field "clips" not found');
		}

		// iterate clips and create mapping
		foreach ($clips as $clip) {
			$this->id_mapping[self::EVENTS][$clip[self::EVENT_ID_OLD]] = $clip[self::EVENT_ID_NEW];
			$this->id_mapping[self::SERIES][$clip[self::SERIES_ID_OLD]] = $clip[self::SERIES_ID_NEW];
		}
	}

	protected function migrateObjectData() {
		global $tree;
		$this->log->write('*Migrate Object Data*');
		$sql = $this->db->query('SELECT * FROM rep_robj_xsca_data');
		while ($rec = $this->db->fetchAssoc($sql)) {
			$ilObjSCast = new ilObjScast($rec['id']);
			$series_id = $this->id_mapping[self::SERIES][$ilObjSCast->getExtId()];
			$parent_id = $tree->getParentId($ilObjSCast->getRefId());
			$this->log->write("migrating scast: title={$ilObjSCast->getTitle()} ref_id={$ilObjSCast->getRefId()} obj_id={$rec['id']} channel_id={$rec['ext_id']} parent_id=$parent_id");
			if (!$series_id) {
				$this->log->write("WARNING: no mapping found for channel_id {$rec['ext_id']}");
				$this->log->write("skip and proceed with next object");
				continue;
			}
			$this->log->write("create ilObjOpenCast..");
			$ilObjOpenCast = new ilObjOpenCast();
			$ilObjOpenCast->setTitle($ilObjSCast->getTitle());
			$ilObjOpenCast->setDescription($ilObjSCast->getDescription());
			$ilObjOpenCast->setOwner($ilObjSCast->getOwner());
			$ilObjOpenCast->create();
			$ilObjOpenCast->createReference();

			$this->log->write("putInTree..");
			$ilObjOpenCast->putInTree($parent_id);
			$ilObjOpenCast->setPermissions($parent_id);


			$this->log->write("create xoctOpenCast..");
			$cast = new xoctOpenCast($ilObjOpenCast->getId());
			$cast->setSeriesIdentifier($series_id);
			$cast->setObjOnline($ilObjSCast->getOnline());
			$cast->setPermissionPerClip($ilObjSCast->getIvt());
			$cast->setPermissionAllowSetOwn($ilObjSCast->getInvitingPossible());
			$cast->setIntroText($ilObjSCast->getIntroductionText());
			$cast->setUseAnnotations($ilObjSCast->getAllowAnnotations());
			$cast->setStreamingOnly($ilObjSCast->getStreamingOnly());
			$cast->create();

			//TODO set producers (crs-admins etc.)
			$this->log->write("opencast creation succeeded: ref_id={$ilObjOpenCast->getRefId()} obj_id={$ilObjOpenCast->getId()} series_id={$cast->getSeriesIdentifier()}");

			$this->migrateGroups($ilObjSCast->getId(), $ilObjOpenCast->getId());
		}
		$this->log->write('Migration of Object Data Succeeded');
	}

	protected function migrateGroups($sca_id, $xoct_id) {
		$this->log->write('migrate groups..');
		foreach (xscaGroup::getAllForObjId($sca_id) as $sca_group) {
			$this->log->write("creating group {$sca_group->getTitle()}..");
			$xoct_group = new xoctIVTGroup();
			$xoct_group->setSerieId($xoct_id);
			$xoct_group->setTitle($sca_group->getTitle());
			$xoct_group->create();
			foreach ($sca_group->getMemberIds() as $member_id) {
				$this->log->write("creating group member $member_id..");
				$xoct_group_participant = new xoctIVTGroupParticipant();
				$xoct_group_participant->setUserId($member_id);
				$xoct_group_participant->setGroupId($xoct_group);
				$xoct_group_participant->create();
			}
		}
		$this->log->write("migration of groups succeeded");
	}

	protected function migrateInvitations() {
		$this->log->write('migrate invitations..');
		$sql = $this->db->query('SELECT * FROM rep_robj_xsca_cmember');
		while ($rec = $this->db->fetchAssoc($sql)) {
			$event_id = $this->id_mapping[self::EVENTS][$rec['clip_ext_id']];
			if (!$event_id) {
				$this->log->write("WARNING: no mapping found for clip_id {$rec['clip_ext_id']}");
				$this->log->write("skip and proceed with next invitation");
				continue;
			}
			$this->log->write("creating invitation for user {$rec['user_id']} and event $event_id");
			$invitation = new xoctInvitation();
			$invitation->setEventIdentifier($event_id);
			$invitation->setUserId($rec['user_id']);
			$invitation->setOwnerId(0);
			$invitation->create();
		}
		$this->log->write('migration of invitations succeeded');
	}
}