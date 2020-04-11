<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Morris Jobke 2013, 2014
 * @copyright Pauli Järvinen 2016 - 2020
 */

namespace OCA\Music\Db;

use OCP\IDBConnection;

class TrackMapper extends BaseMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'music_tracks', '\OCA\Music\Db\Track', 'title');
	}

	/**
	 * Override the base implementation
	 * @see \OCA\Music\Db\BaseMapper::selectEntities()
	 * @param string $condition
	 * @param string|null $extension
	 */
	protected function selectEntities($condition, $extension=null) {
		return "SELECT `track`.*, `file`.`name` AS `filename`, `file`.`size`
				FROM `*PREFIX*music_tracks` `track`
				INNER JOIN `*PREFIX*filecache` `file`
				ON `track`.`file_id` = `file`.`fileid`
				WHERE $condition $extension";
	}

	/**
	 * @param integer $artistId
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByArtist($artistId, $userId) {
		$sql = $this->selectUserEntities('`artist_id` = ? ', 'ORDER BY LOWER(`track`.`title`)');
		$params = [$userId, $artistId];
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $albumId
	 * @param string $userId
	 * @param integer|null $artistId
	 * @return Track[]
	 */
	public function findAllByAlbum($albumId, $userId, $artistId = null) {
		$condition = '`track`.`album_id` = ?';
		$params = [$userId, $albumId];

		if ($artistId !== null) {
			$condition .= ' AND `track`.`artist_id` = ? ';
			$params[] = $artistId;
		}

		$sql = $this->selectUserEntities($condition, 
				'ORDER BY `track`.`disk`, `track`.`number`, LOWER(`track`.`title`)');
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param integer $folderId
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByFolder($folderId, $userId) {
		$sql = $this->selectUserEntities('`file`.`parent` = ?', 'ORDER BY LOWER(`track`.`title`)');
		$params = [$userId, $folderId];
		return $this->findEntities($sql, $params);
	}

	/**
	 * @param string $userId
	 * @return int[]
	 */
	public function findAllFileIds($userId) {
		$sql = 'SELECT `file_id` FROM `*PREFIX*music_tracks` WHERE `user_id` = ?';
		$result = $this->execute($sql, [$userId]);

		return \array_map(function ($i) {
			return $i['file_id'];
		}, $result->fetchAll());
	}

	/**
	 * Find a track of user matching a file ID
	 * @param integer $fileId
	 * @param string $userId
	 * @return Track
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findByFileId($fileId, $userId) {
		$sql = $this->selectUserEntities('`track`.`file_id` = ?');
		$params = [$userId, $fileId];
		return $this->findEntity($sql, $params);
	}

	/**
	 * Find tracks of user with multiple file IDs
	 * @param integer[] $fileIds
	 * @param string[] $userIds
	 * @return Track[]
	 */
	public function findByFileIds($fileIds, $userIds) {
		$sql = $this->selectEntities(
				'`track`.`user_id` IN ' . $this->questionMarks(\count($userIds)) .
				' AND `track`.`file_id` IN '. $this->questionMarks(\count($fileIds)));
		$params = \array_merge($userIds, $fileIds);
		return $this->findEntities($sql, $params);
	}

	/**
	 * Finds tracks of all users matching one or multiple file IDs
	 * @param integer[] $fileIds
	 * @return Track[]
	 */
	public function findAllByFileIds($fileIds) {
		$sql = $this->selectEntities('`track`.`file_id` IN '.
				$this->questionMarks(\count($fileIds)));
		return $this->findEntities($sql, $fileIds);
	}

	/**
	 * @param integer $artistId
	 * @return integer
	 */
	public function countByArtist($artistId) {
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`artist_id` = ?';
		$result = $this->execute($sql, [$artistId]);
		$row = $result->fetch();
		return $row['count'];
	}

	/**
	 * @param integer $albumId
	 * @return integer
	 */
	public function countByAlbum($albumId) {
		$sql = 'SELECT COUNT(*) AS count FROM `*PREFIX*music_tracks` `track` '.
			'WHERE `track`.`album_id` = ?';
		$result = $this->execute($sql, [$albumId]);
		$row = $result->fetch();
		return $row['count'];
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @return Track[]
	 */
	public function findAllByNameRecursive($name, $userId) {
		$condition = '(`track`.`artist_id` IN (SELECT `id` FROM `*PREFIX*music_artists` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' `track`.`album_id` IN (SELECT `id` FROM `*PREFIX*music_albums` WHERE LOWER(`name`) LIKE LOWER(?)) OR '.
						' LOWER(`track`.`title`) LIKE LOWER(?) )';
		$sql = $this->selectUserEntities($condition, 'ORDER BY LOWER(`track`.`title`)');
		$name = '%' . $name . '%';
		$params = [$userId, $name, $name, $name];
		return $this->findEntities($sql, $params);
	}

	/**
	 * Returns track specified by name and/or artist name
	 * @param string|null $name the name of the track
	 * @param string|null $artistName the name of the artist
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Track|null Mathing track if the criteria uniquely defines one
	 */
	public function findByNameAndArtistName($name, $artistName, $userId) {
		$sqlConditions = '';
		$params = [$userId];

		if (!empty($name)) {
			$sqlConditions .= 'LOWER(`track`.`title`) LIKE LOWER(?) ';
			$params[] = $name;
		}

		if (!empty($artistName)) {
			if (!empty($sqlConditions)) {
				$sqlConditions .= ' AND ';
			}
			$sqlConditions .= '`track`.`artist_id` IN (SELECT `id` FROM `*PREFIX*music_artists` WHERE LOWER(`name`) LIKE LOWER(?))';
			$params[] = $artistName;
		}

		$sql = $this->selectUserEntities($sqlConditions);
		return $this->findEntity($sql, $params);
	}

	/**
	 * Finds all track IDs of the user along with the parent folder ID of each track
	 * @param string $userId
	 * @return array where keys are folder IDs and values are arrays of track IDs
	 */
	public function findTrackAndFolderIds($userId) {
		$sql = 'SELECT `track`.`id` AS id, `file`.`parent` AS parent '.
				'FROM `*PREFIX*music_tracks` `track` '.
				'JOIN `*PREFIX*filecache` `file` '.
				'ON `track`.`file_id` = `file`.`fileid` '.
				'WHERE `track`.`user_id` = ?';

		$rows = $this->execute($sql, [$userId])->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			$result[$row['parent']][] = $row['id'];
		}

		return $result;
	}

	/**
	 * Find names and paths of the file system nodes with given IDs within the given storage
	 * @param int[] $nodeIds
	 * @param string $storageId
	 * @return array where keys are the node IDs and values are associative arrays
	 *         like { 'name' => string, 'path' => string };
	 */
	public function findNodeNamesAndPaths($nodeIds, $storageId) {
		$result = [];

		if (!empty($nodeIds)) {
			$sql = 'SELECT `fileid`, `name`, `path` '.
					'FROM `*PREFIX*filecache` `filecache` '.
					'JOIN `*PREFIX*storages` `storages` '.
					'ON `filecache`.`storage` = `storages`.`numeric_id` '.
					'WHERE `storages`.`id` = ? '.
					'AND `filecache`.`fileid` IN '. $this->questionMarks(\count($nodeIds));

			$rows = $this->execute($sql, \array_merge([$storageId], $nodeIds))->fetchAll();

			foreach ($rows as $row) {
				$result[$row['fileid']] = [
					'name' => $row['name'],
					'path' => $row['path']
				];
			}
		}

		return $result;
	}

	/**
	 * @see \OCA\Music\Db\BaseMapper::findUniqueEntity()
	 * @param Track $track
	 * @return Track
	 */
	protected function findUniqueEntity($track) {
		return $this->findByFileId($track->getFileId(), $track->getUserId());
	}
}
