<?php

/**
 * Write the cached file to the 'File' SS database. This allows the file to be hosted on
 * external file locations (such as Amazon S3) when syncing on large projects.
 */
class ListFilterCacheFile extends ListFilterCache {
	private static $folder_name = 'Uploads';

	public function save($data, $cacheKey) {
		$name = $cacheKey;

		$folderName = Config::inst()->get(__CLASS__, 'folder_name');
		$folder = Folder::find_or_make($folderName); // relative to assets

		$filename = $folder->Filename.$name;
		$file = File::get()->filter(array(
			'Name' => $name,
		))->first();
		if (!$file || !$file->exists()) {
			$file = File::create();
		}
		$file->Name = $name;
		$file->Filename = $filename;
		$file->ParentID = ($folder && $folder->exists()) ? $folder->ID : null;
		file_put_contents($file->getFullPath(), $data);
		$file->write();
		// Upload to S3/CDNContent for 'silverstripe-australia/cdncontent' module
		$file->onAfterUpload();
		return $file;
	}

	public function load($cacheKey) {
		$file = $this->loadFile($cacheKey);
		$data = file_get_contents($file->getFullPath());
		return $data;
	}

	public function loadFile($cacheKey) {
		$file = File::get()->filter(array(
			'Name' => $cacheKey,
		))->first();
		return $file;
	}
}