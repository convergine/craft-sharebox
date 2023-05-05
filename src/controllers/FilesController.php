<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\controllers;

use convergine\sharebox\ShareBox;
use Craft;
use craft\web\Controller;


class FilesController extends Controller
{
	// Public Methods
	// =========================================================================

	public function actionIndex() {

		$provider  = ShareBox::getInstance()->getProvider();
		$variables = [
			'title'       => 'Files',
			'files_table' => ''
		];
		if ( $provider === false ) {
			$variables['_noticeMessage'] = \Craft::$app->view->renderTemplate( 'convergine-sharebox/_notices/provider' );

		} else {

			if ( $provider->credentialsIsSet() ) {
				$variables ['files_table'] = $provider->getFilesTable( 0 );
			} else {
				$variables['_noticeMessage'] = \Craft::$app->view->renderTemplate( $provider->getNoticeTemplatePath() );
			}
		}

		return $this->renderTemplate('convergine-sharebox/_files/index', $variables);
	}

	public function actionGetFoldersArray(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$move_folder = $post['move_folder'];
		return $this->asJson([
			'move_folder'=>$move_folder,
			'folders'=>ShareBox::getInstance()->getProvider()->getFoldersArray($move_folder),
			'folder_path'=>ShareBox::getInstance()->getProvider()->getFolderPath($move_folder)
		]);
	}

	public function actionGetFileFoldersArray(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$move_file = $post['move_file'];
		$file_data = ShareBox::getInstance()->getProvider()->getFile($move_file);
		return $this->asJson([
			'move_file'=>$move_file,
			'folders'=>ShareBox::getInstance()->getProvider()->getFileFoldersArray($move_file),
			'folder_path'=>ShareBox::getInstance()->getProvider()->getFolderPath($file_data['folder_id'])
		]);
	}

	public function actionMoveFolder(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$from = $post['from'];
		$to = $post['to'];
		$res = ShareBox::getInstance()->getProvider()->moveFolder((int)$from,(int)$to);
		return $this->asJson($res);
	}

	public function actionMoveFile(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$file = $post['from'];
		$to = $post['to'];
		$res = ShareBox::getInstance()->getProvider()->moveFile((int)$file,(int)$to);
		return $this->asJson($res);
	}

	public function actionUploadFile(){

		$this->requirePostRequest();
		$response = ShareBox::getInstance()->getProvider()->saveFile();
		return $this->asJson($response);
	}

	public function actionGetFilesTable(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$file_service = ShareBox::getInstance()->getProvider();
		$folder_parent_id = $post['folder_id']==0?-1:$file_service->getFolder($post['folder_id'])['parent_id'];
		$data=[
			'table'=>$file_service->getFilesTable($post['folder_id']),
			'parent_id'=>$folder_parent_id,
			'folder_path'=>$file_service->getFolderBradCrumbs($post['folder_id']),
		];
		return $this->asJson($data);
	}

	public function actionCreateFolder(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$file_service = ShareBox::getInstance()->getProvider();
		$response = $file_service->createFolder($post);
		if($response['res']){
			$response['table'] = $file_service->getFilesTable($post['folder_id']);
		}

		return $this->asJson($response);
	}

	public function actionRemoveFolder(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$file_service = ShareBox::getInstance()->getProvider();
		$response = $file_service->removeFolder($post);
		if($response['res']){
			$response['table'] = $file_service->getFilesTable($response['parent_id']);
		}

		return $this->asJson($response);
	}

	public function actionRemoveFile(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$file_service = ShareBox::getInstance()->getProvider();
		$response = $file_service->removeFile($post);
		if($response['res']){
			$response['table'] = $file_service->getFilesTable($response['folder_id']);
		}

		return $this->asJson($response);
	}

	public function actionDownload(){
		$request = \Craft::$app->getRequest();
		$uid = $request->get('uid');
		$inline = $request->get('inline');
		$inline = $inline=='false'?false:true;
		$file_data = ShareBox::getInstance()->getProvider()->getFileContent($uid);
		if(is_string($file_data)){
			return $file_data;
		}

		$mime = $file_data['mime'];
		$options=[
			'mimeType' => $mime,
			'inline'=>$inline,
			'fileSize'=>$file_data['size']
		];

		return Craft::$app->response->sendStreamAsFile($file_data['stream'],$file_data['name'],$options);
	}
}
