<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\services;


use convergine\sharebox\ShareBox;
use craft\base\Component;
use craft\db\Query;

class FrontFilesService extends Component
{

	public function getFilesTable($category_id){
		$file_service = ShareBox::getInstance()->fileService;
		$data = [];
		$data['files_list'] = $file_service->getFiles($category_id);
		$data['folders'] = $file_service->getFolders($category_id);

		return \Craft::$app->view->renderTemplate('convergine-sharebox/_frontend/table.twig',$data);
	}


	public function addStat( $action, $description = '', $id_file = 0 ) {
		\Craft::$app->db->createCommand()->insert( '{{%conv_stat}}', [
			'ip'      => $_SERVER['REMOTE_ADDR'],
			'action'  => $action,
			'details' => $description,
			'id_file' => $id_file
		] )->execute();
	}

	public function getFolderBradCrumbs($id_folder,$root_folder_id){
		$path = "";
		if($id_folder ==$root_folder_id ){
			$folders_list =[];
		}else{
			$folders_list = $this->getFolderPathArray($id_folder,[],$root_folder_id);
		}

		$folders_list[]=['name'=>'Root Folder','id'=>$root_folder_id,'parent_id'=>0];
		$folders_list = array_reverse($folders_list);
		$i = 1;
		foreach ($folders_list as $folder){
			if($i==count($folders_list)){
				$path.=" / {$folder['name']}";
			}else{
				$path.=" / <a href='javascript:;' class='open_folder' data-parent_id=\"{$folder['parent_id']}\" data-id=\"{$folder['id']}\">{$folder['name']}</a>";
			}

			$i++;
		}
		return ltrim($path,' / ');
	}

	public function getFolderPathArray( $id_folder, $prefix = [], $root_folder_id ) {
		$folder_data = $this->getFolder( $id_folder );
		if ( $folder_data ) {
			$prefix[] = $folder_data;
			if ( $folder_data['parent_id'] > $root_folder_id ) {
				return $this->getFolderPathArray( $folder_data['parent_id'], $prefix, $root_folder_id );
			}
			return $prefix;
		}

		return $prefix;
	}
	public function getFolder($folder_id){
		$folder = (new Query())->select( [ '*' ] )
		                      ->from( '{{%conv_folders}}' )
		                      ->where(['id'=>$folder_id])
		                      ->one();
		return $folder;
	}
}