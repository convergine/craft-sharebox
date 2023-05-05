<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\services;

use craft\base\Component;
use craft\db\Query;
use yii\base\Exception;

class FileService extends Component
{

	/**
	 * @param $category_id
	 *
	 * @return string
	 * @throws Exception
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
	public function getFilesTable( $category_id ) {

		$data = [
			'files'   => $this->getFiles( $category_id ),
			'folders' => $this->getFolders( $category_id )
		];

		return \Craft::$app->view->renderTemplate( 'convergine-sharebox/_files/table.twig', $data );
	}

	/**
	 * @param $folder_id
	 *
	 * @return array|bool|mixed
	 */
	public function getFiles($folder_id){

		$files = (new Query())->select( [ '*' ] )
		                     ->from( '{{%conv_files}}' )
		                     ->where(['folder_id'=>$folder_id])
		                     ->orderBy(['name' => SORT_ASC])
		                     ->all();
		return $files;
	}

	/**
	 * @param $folder_id
	 *
	 * @return array|bool|mixed
	 */
	public function getFolders($folder_id){

		$files = (new Query())->select( [ '*' ] )
		                     ->from( '{{%conv_folders}}' )
		                     ->where(['parent_id'=>$folder_id])
		                     ->orderBy(['name' => SORT_ASC])
		                     ->all();
		return $files;
	}

	/**
	 * @param $folder_id
	 *
	 * @return array|bool|mixed
	 */
	public function getFolder($folder_id){

		$folder = (new Query())->select( [ '*' ] )
		                     ->from( '{{%conv_folders}}' )
		                     ->where(['id'=>$folder_id])
		                     ->one();
		return $folder;
	}

	/**
	 * @param $uid
	 *
	 * @return array|bool|mixed
	 */
	public function getFolderByUid($uid){

		$folder = (new Query())->select( [ '*' ] )
		                      ->from( '{{%conv_folders}}' )
		                      ->where(['uid'=>$uid])
		                      ->one();
		return $folder;
	}

	/**
	 * @param $file_id
	 *
	 * @return array|bool|mixed
	 */
	public function getFile($file_id){

		$folder = (new Query())->select( [ '*' ] )
		                      ->from( '{{%conv_files}}' )
		                      ->where(['id'=>$file_id])
		                      ->one();
		return $folder;
	}

	/**
	 * @param $uid
	 *
	 * @return array|bool|mixed
	 */
	public function getFileByUid($uid){

		$file = (new Query())->select( [ '*' ] )
		                      ->from( '{{%conv_files}}' )
		                      ->where(['uid'=>$uid])
		                      ->one();
		return $file;
	}

	/**
	 * @param $post
	 *
	 * @return array
	 * @throws \yii\db\Exception
	 */
	public function createFolder($post){

		$response = ['res'=>false,'msg'=>\Craft::t( 'convergine-sharebox','Error creating folder')];
		$parent_id = $post['folder_id'];
		$folder_name = $post['folder_name'];
		$folder = (new Query())->select( [ '*' ] )
		                     ->from( '{{%conv_folders}}' )
		                     ->where(['parent_id'=>$parent_id,'name'=>$folder_name])
		                     ->one();
		if($folder){
			$response['msg'] = \Craft::t( 'convergine-sharebox','Folder already exists');
			return $response;
		}
		$res = \Craft::$app->db->createCommand()->insert( '{{%conv_folders}}', [
			'name'      => $folder_name,
			'parent_id' => $parent_id
		] )->execute();
		if($res){
			$response['res']=true;
			$response['msg']=\Craft::t( 'convergine-sharebox','Folder created');
		}

		return $response;
	}

	/**
	 * @param $id_folder
	 * @param $prefix
	 *
	 * @return array|mixed
	 */
	public function getFolderPathArray($id_folder,$prefix=[]){

		$folder_data = $this->getFolder($id_folder);
		if($folder_data){
			$prefix[]=$folder_data;
			if($folder_data['parent_id']!=0){
				return $this->getFolderPathArray($folder_data['parent_id'],$prefix);
			}else{
				return $prefix;
			}
		}else{
			return $prefix;
		}
	}

	/**
	 * @param $id_folder
	 * @param $root
	 *
	 * @return string
	 */
	public function getFolderPath($id_folder,$root=''){

		$path = "";
		$folders_list = $this->getFolderPathArray($id_folder);
		$folders_list = array_reverse($folders_list);
		foreach ($folders_list as $folder){
			$path.="{$folder['name']}/";
		}
		return $root.$path;
	}

	/**
	 * @param $id_folder
	 *
	 * @return string
	 */
	public function getFolderBradCrumbs($id_folder){

		$path = "";
		$folders_list = $this->getFolderPathArray($id_folder);
		$folders_list[]=['name'=>'Root Folder','id'=>0,'parent_id'=>0];
		$folders_list = array_reverse($folders_list);
		$i = 1;
		foreach ($folders_list as $folder){
			if($i==count($folders_list)){
				$path.=" / {$folder['name']}";
			}else{
				$path.=" / <a href='javascript:;' class='conv_open_folder' data-parent_id=\"{$folder['parent_id']}\" data-id=\"{$folder['id']}\">{$folder['name']}</a>";
			}

			$i++;
		}
		return ltrim($path,' / ');
	}

	/**
	 * @param $folder_id
	 *
	 * @return true
	 * @throws \yii\db\Exception
	 */
	public function removeFoldersFiles($folder_id){

		$child_folders = $this->getFoldersByParent($folder_id);
		if($child_folders){
			foreach ($child_folders as $folder){
				$this->removeFoldersFiles($folder['id']);
			}
		}
		$folder_files = $this->getFiles($folder_id);
		foreach ($folder_files as $file){

			\Craft::$app->db->createCommand()->delete('{{%conv_files}}',[
				'id'=>$file['id']
			])->execute();
		}

		\Craft::$app->db->createCommand()->delete('{{%conv_folders}}',[
			'id'=>$folder_id
		])->execute();
		return true;
	}

	/**
	 * @param $folder_id
	 *
	 * @return array
	 */
	public function getFoldersByParent($folder_id){

		$folders = (new Query())->select( [ '*' ] )
		                      ->from( '{{%conv_folders}}' )
		                      ->where(['parent_id'=>$folder_id])
		                      ->all();
		return $folders;
	}

	/**
	 * @param $bytes
	 * @param $decimals
	 *
	 * @return string
	 */
	public function getFileSize( $bytes ,$decimals=2 ) {

		$size   = array( 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
		$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

		return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];

	}

	/**
	 * @param $uid
	 *
	 * @return string
	 */
	public function getDownloadUrl($uid){
		return "/actions/convergine-sharebox/frontend/download?inline=true&uid=$uid";
	}


	/**
	 * @param int $moved_folder_id - Moved older ID
	 * @param int $target_folder_id - Target Folder ID
	 *
	 * @return bool
	 * @throws \yii\db\Exception
	 */
	public function moveFolder( $moved_folder_id, $target_folder_id ) {

		$result = [
			'res'  => false,
			'msg'  => \Craft::t( 'convergine-sharebox','Some error accrued. Please resubmit'),
			'from' => $moved_folder_id,
			'to'   => $target_folder_id
		];

		$target_folder_data = $this->getFolder( $moved_folder_id );
		if ( $target_folder_id === 0 ) {
			$folder_data['parent_id'] = 0;
		} else {
			$folder_data = $this->getFolder( $target_folder_id );
		}

		if ( $folder_data['parent_id'] === $target_folder_data['parent_id'] && $folder_data['parent_id']!=0) {
			$result['msg'] = \Craft::t( 'convergine-sharebox','You can\'t move file to selected folder');

			return $result;
		}

		\Craft::$app->db->createCommand()->update( '{{%conv_folders}}', [
			'parent_id' => $target_folder_id
		], [
			'id' => $moved_folder_id
		] )->execute();

		$result['res'] = $this->copyAndMoveFolderFiles( $moved_folder_id );

		return $result;
	}

	/**
	 * @param $moved_file_id - Moved file ID
	 * @param $target_folder_id - Target folder ID
 	 *
	 * @return array
	 * @throws \yii\db\Exception
	 */
	public function moveFile($moved_file_id, $target_folder_id){
		$result = [
			'res'  => false,
			'msg'  => \Craft::t( 'convergine-sharebox','Some error accrued. Please resubmit'),
			'from' => $moved_file_id,
			'to'   => $target_folder_id
		];

		$target_file_data = $this->getFile( $moved_file_id );

		if ( $target_folder_id === $target_file_data['folder_id'] ) {
			$result['msg'] = \Craft::t( 'convergine-sharebox','You can\'t move file to selected folder');

			return $result;
		}

		// check if file with same name already exits
		$file_exits = (new Query())->select( [ '*' ] )
		                        ->from( '{{%conv_files}}' )
		                        ->where([
									'folder_id'=>$target_folder_id,
			                        'name'=>$target_file_data['name']
		                        ])
		                        ->one();
		if($file_exits){
			$result['msg'] = \Craft::t( 'convergine-sharebox','File with same name already exists in target folder');

			return $result;
		}

		\Craft::$app->db->createCommand()->update( '{{%conv_files}}', [
			'folder_id' => $target_folder_id
		], [
			'id' => $moved_file_id
		] )->execute();

		$result['res'] = $this->copyAndMoveFile( $moved_file_id );

		return $result;
	}

	/**
	 * @param $folder_id
	 *
	 * @return bool
	 */
	public function copyAndMoveFolderFiles($folder_id){

		$child_folders = $this->getFoldersByParent($folder_id);
		if($child_folders){
			foreach ($child_folders as $folder){
				$this->copyAndMoveFolderFiles($folder['id']);
			}
		}
		$folder_files = $this->getFiles($folder_id);
		foreach ($folder_files as $file){
			$file_path = $this->getFolderPath($file['folder_id']).$file['name'];

			$res = $this->moveBlob($file['id'],$file['path'],$file_path);
			if($res===false){
				return false;
			}
		}
		return true;
	}

	/**
	 * @param $file_id
	 *
	 * @return bool
	 */
	public function copyAndMoveFile($file_id){
		$file = $this->getFile($file_id);
		$file_path = $this->getFolderPath($file['folder_id']).$file['name'];
		$res = $this->moveBlob($file_id,$file['path'],$file_path);
		if($res===false){
			return false;
		}
		return true;
	}

	public function getFoldersArray($target_folder,$parent_id=0, $folders=['ID:0'=>'Root'],$i=0){

		$folder_data = $this->getFolder($target_folder);
		$child_folders = $this->getFoldersByParent($parent_id);
		if($child_folders){
			$i++;
			foreach ($child_folders as $folder){
				if($target_folder==$folder['id'] || ($folder_data['parent_id']>0 && $folder_data['parent_id']==$folder['id'])){
					continue;
				}
				$prefix = $parent_id==0?"":"|";
				for($j=1;$j<$i;$j++){
					$prefix .="-";
				}
				$folders["ID:".$folder['id']] = "&nbsp;&nbsp;&nbsp;".$prefix.$folder['name'];

				$folders = $this->getFoldersArray($target_folder,$folder['id'],$folders,$i);
			}
		}
		return $folders;
	}

	public function getFileFoldersArray($target_file,$parent_id=0, $folders=['ID:0'=>'Root'],$i=0){

		$file_data = $this->getFile($target_file);
		$child_folders = $this->getFoldersByParent($parent_id);
		if($child_folders){
			$i++;
			foreach ($child_folders as $folder){
				$prefix = $parent_id==0?"":"|";
				for($j=1;$j<$i;$j++){
					$prefix .="-";
				}
				$folders["ID:".$folder['id']] = "&nbsp;&nbsp;&nbsp;".$prefix.$folder['name'];

				$folders = $this->getFileFoldersArray($target_file,$folder['id'],$folders,$i);
			}
		}
		return $folders;
	}

}