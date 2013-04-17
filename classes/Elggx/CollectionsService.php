<?php

/**
 * Use or create collections
 *
 * @see Elgg_Collection
 *
 * @access private
 *
 * @package    Elgg.Core
 * @subpackage Collections
 */
class Elggx_CollectionsService {

	/**
	 * @var Elggx_Collection[] cached references to collections
	 */
	protected $instances = array();

	/**
	 * Get a reference to a collection if it exists, and the current user can see (or can edit it)
	 *
	 * @param ElggEntity $entity
	 * @param string $name
	 * @return Elggx_Collection|null
	 * @throws InvalidArgumentException
	 */
	public function fetch(ElggEntity $entity, $name) {
		if (!$name) {
			throw new InvalidArgumentException('$name must not be empty');
		}
		if (!$entity->guid) {
			throw new InvalidArgumentException('$entity must have a GUID (have been saved)');
		}
		// common case
		if (Elggx_Collection::canSeeExistenceMetadata($entity, $name)) {
			return $this->factory($entity, $name, true);
		}
		// This allows us to support hidden/differently owned metadata, but make sure anyone who can
		// edit the entity can always access/edit the collection. (the metadata is just an implementation
		// detail)
		if ($entity->canEdit() && $this->exists($entity, $name)) {
			return $this->factory($entity, $name, true);
		}
		return null;
	}

	/**
	 * Does the collection exist? This does not imply the current user can access it.
	 *
	 * @param ElggEntity|int $entity entity or GUID
	 * @param $name
	 * @return bool
	 * @throws InvalidArgumentException
	 */
	public function exists($entity, $name) {
		if (!$name) {
			throw new InvalidArgumentException('$name must not be empty');
		}
		if (!$entity->guid) {
			throw new InvalidArgumentException('$entity must have a GUID (have been saved)');
		}
		$ia = elgg_set_ignore_access(true);
		if (!($entity instanceof ElggEntity)) {
			$entity = get_entity($entity);
		}
		$exists = ($entity && Elggx_Collection::canSeeExistenceMetadata($entity, $name));
		elgg_set_ignore_access($ia);
		return $exists;
	}

	/**
	 * Create (or fetch an existing) named collection on an entity. Good for creating a collection
	 * on demand for editing.
	 *
	 * @param ElggEntity $entity
	 * @param string $name
	 * @return Elggx_Collection|null null if user is not permitted to create
	 */
	public function create(ElggEntity $entity, $name) {
		$coll = $this->fetch($entity, $name);
		if (!$coll && $entity->canEdit()) {
			$coll = $this->factory($entity, $name, false);
		}
		return $coll;
	}

	/**
	 * Get the entity_guid and name of all collections containing this item.
	 *
	 * @todo untested!!!
	 * @todo figure out how to make this scale: limit collection entities to site, groups, and current user?
	 *
	 * @param int $item_guid
	 * @param int $collection_owner_guid
	 *
	 * @return array
	 */
	public function getCollectionsByItem($item_guid, $collection_owner_guid = 0) {
		$item_guid = (int)$item_guid;
		$collection_owner_guid = (int)$collection_owner_guid;
		$dbprefix = elgg_get_config('dbprefix');

		$relationship_prefix = Elggx_Collection::RELATIONSHIP_NAME_PREFIX;
		$relationship_prefix_len = strlen($relationship_prefix);

		$collection_name_start_pos = strlen(Elggx_Collection::METADATA_NAME_PREFIX) + 1;
		$collection_hash_start_pos = $relationship_prefix_len + 1;

		$relationship_wheres = array(
			"SUBSTRING(relationship, 1, $relationship_prefix_len) = '$relationship_prefix'",
			"guid_two = $item_guid"
		);
		if ($collection_owner_guid) {
			$relationship_wheres[] = "guid_one = $collection_owner_guid";
		}
		$relationship_where_condition = implode(' AND ', $relationship_wheres);

		$md_is_visible_condition = get_access_sql_suffix('md');

		$sql = "
			SELECT
				md.entity_guid,
				SUBSTRING(mdn.string, $collection_name_start_pos) AS coll_name
			FROM {$dbprefix}metadata md
			JOIN {$dbprefix}metastrings mdn ON md.name_id = mdn.id
			JOIN {$dbprefix}metastrings mdv ON md.value_id = mdv.id
			WHERE $md_is_visible_condition
				AND mdv.string IN (
					SELECT SUBSTRING(relationship, $collection_hash_start_pos)
					FROM {$dbprefix}entity_relationships
					WHERE $relationship_where_condition
				)
		";
		return get_data($sql);
	}

	/**
	 * Makes sure only one instance is handed out of each possible collection
	 *
	 * @param ElggEntity $entity
	 * @param string $name
	 * @param bool $has_metadata
	 * @return Elggx_Collection
	 *
	 * @access private
	 */
	protected function factory(ElggEntity $entity, $name, $has_metadata = false) {
		$key = $entity->guid . '|' . $name;
		if (!isset($this->instances[$key]) || $this->instances[$key]->isDeleted()) {
			$this->instances[$key] = new Elggx_Collection($entity, $name, $has_metadata);
		}
		return $this->instances[$key];
	}

	/**
	 * @param ElggEntity $entity
	 * @param string $name
	 * @return bool
	 */
	public function delete(ElggEntity $entity, $name) {
		$coll = $this->create($entity, $name);
		if ($coll) {
			$coll->delete();
			$key = $entity->guid . '|' . $name;
			unset($this->instances[$key]);
		}
		return true;
	}

	/*public function deleteAll(ElggEntity $entity) {
		if (!$entity->canEdit()) {
			return false;
		}
		$this->instances = array();
		elgg_delete_metadata(array(
			'guids' => $entity->guid,
			'wheres' => array(

			),
		));
	}/**/
}
