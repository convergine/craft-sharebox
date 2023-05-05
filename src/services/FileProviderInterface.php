<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */
namespace convergine\sharebox\services;

interface FileProviderInterface {
	/**
	 * @return bool
	 */
	public function credentialsIsSet(): bool;

	public function getNoticeTemplatePath(): string;

	/**
	 * @param $uid
	 * @param $count
	 *
	 * @return array|string
	 */
	public function getFileContent($uid,$count=false):array|string;

	/** Save and sent file to provider
	 * return array
	 * [ 
	 *  'res' => bool, - result
	 *  'msg' => string, - message string
	 *  'file_id' => int - saved file ID
	 * ]
	 * 
	 * @return array
	 * @throws \yii\db\Exception
	 */
	public function saveFile():array;

	/** Remove folder
	 *
	 * return array
	 * [
	 *  'res' => bool, - result
	 *  'msg' => string, - message string
	 *  'parent_id' => int - removed folder parent ID
	 * ]
	 * 
	 * @param array $post - ['folder_id']
	 *
	 * @return array
	 * @throws \yii\db\Exception
	 */
	public function removeFolder($post):array;

	/** Remove file
	 *
	 * return array
	 * [
	 *  'res' => bool, - result
	 *  'msg' => string, - message string
	 *  'folder_id' => int - removed file folder ID
	 * ]
	 *
	 * @param array $post - ['file_id']
	 *
	 * @return array
	 * @throws \yii\db\Exception
	 */
	public function removeFile($post):array;

	/**Move file
	 * 
	 * @param int $file_id - file to copy ID
	 * @param string $from - current path
	 * @param string $to - target path
	 *
	 * @return bool
	 * @throws \yii\db\Exception
	 */
	public function moveBlob($file_id,$from,$to):bool;
}