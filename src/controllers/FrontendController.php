<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\controllers;

use convergine\sharebox\ShareBox;
use Craft;
use craft\web\Controller;

class FrontendController extends Controller
{

	/**
	 * @var    bool|array Allows anonymous access to this controller's actions.
	 *         The actions must be in 'kebab-case'
	 * @access protected
	 */
	protected array|int|bool $allowAnonymous = [
		'index',
		'check-password',
		'logout',
		'get-files-table',
		'download',
		'video-view'
	];
	// Public Methods
	// =========================================================================


	public function actionLogout(){
		$this->requirePostRequest();
        ShareBox::getInstance()->frontendService->logout();
		return $this->redirectToPostedUrl();
	}


	public function actionCheckPassword(){
		$this->requirePostRequest();
		$request = Craft::$app->getRequest();
		$post = $request->post();
		$fd = $post['fd']??'';
		$ff = $post['ff']??'';
		$result = ShareBox::getInstance()->frontendService->checkLogin($post);
		$files_service = ShareBox::getInstance()->getProvider();
		if(!Craft::$app->getSession()->get('root_folder_id')){
			if($fd){
				$folder_data = $files_service->getFolderByUid($fd);
				Craft::$app->getSession()->set('root_folder_id',$folder_data['id']);
			}else{
				Craft::$app->getSession()->set('root_folder_id',0);
			}
		}
		if(!Craft::$app->getSession()->get('file_id')){
			if($ff){
				$file_data = $files_service->getFileByUid($ff);
				Craft::$app->getSession()->set('file_id',$file_data['id']);
			}
		}


		if($result['res']){
			Craft::$app->session->setFlash('success', $result['msg']);
			Craft::$app->getSession()->setFlash('success',$result['msg']);
			Craft::$app->getSession()->set('logged_in',true);


		}else{
			Craft::$app->session->setFlash('errors', $result['msg']);
			Craft::$app->getSession()->setError($result['msg']);
		}

		return $this->redirectToPostedUrl();
	}

	public function actionGetFilesTable(){

		if(!ShareBox::getInstance()->frontendService->isLoggedIn()){

			return $this->asJson(['res'=>false]);
		}

		$root_folder_id = Craft::$app->getSession()->get('root_folder_id')??0;
		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$file_service = ShareBox::getInstance()->getProvider();
		$file_frontend_service = ShareBox::getInstance()->frontFilesService;
		if($post['folder_id']<$root_folder_id){
			return $this->asJson(['res'=>false]);
		}
		$folder_parent_id = $post['folder_id']==$root_folder_id?-1:
			$file_service->getFolder($post['folder_id'])['parent_id'];
		$data=[
			'res'=>true,
			'table'=>$file_frontend_service->getFilesTable($post['folder_id']),
			'parent_id'=>$folder_parent_id,
			'folder_path'=>$file_frontend_service->getFolderBradCrumbs($post['folder_id'],$root_folder_id),
		];
		return $this->asJson($data);
	}

	public function actionDownload($id = null){

		if(!ShareBox::getInstance()->frontendService->isLoggedIn()){
			$settings = ShareBox::getInstance()->getSettings();
			return $this->redirect($settings->files_page_url);
		}
		$request = \Craft::$app->getRequest();
		$uid = $id;
		$inline = $request->get('inline');
		$inline = $inline=='false'?false:true;
		$file_data = ShareBox::getInstance()->getProvider()->getFileContent($uid,true);
		if(!isset($file_data['stream'])){
			return $file_data;
		}

		$options=[
			'mimeType' => $file_data['mime'],
			'inline'=>$inline,
			'fileSize'=>$file_data['size']
		];

		return Craft::$app->response->sendStreamAsFile($file_data['stream'],$file_data['name'],$options);
	}

}
