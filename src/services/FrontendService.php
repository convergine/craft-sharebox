<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\services;

use convergine\sharebox\ShareBox;
use craft\base\Component;
use GuzzleHttp\Client;

class FrontendService extends Component {

	private function _getLoginForm() {

		$data = [
			'use_recaptcha' => ShareBox::getInstance()->getSettings()->enable_recaptcha
		];

		return \Craft::$app->view->renderTemplate( 'convergine-sharebox/_frontend/login.twig', $data );

	}

	private function _getContent() {
		$content     = \Craft::t( 'convergine-sharebox',"Incorrect link. No data found");
		$error = false;
		$root_folder = \Craft::$app->getSession()->get( 'root_folder_id' );
		if ( ! $root_folder ) {
			$root_folder = 0;
		}
		$file_id = \Craft::$app->getSession()->get( 'file_id' );
		if(!$error) {
			if ( $file_id !== null ) {
				$file_data = ShareBox::getInstance()->fileService->getFile( $file_id );
				$data      = [
					'file'        => $file_data,
					'view_logout' => ShareBox::getInstance()->getSettings()->use_password
				];
				$content   = \Craft::$app->view->renderTemplate( 'convergine-sharebox/_frontend/file.twig', $data );
			} elseif ( $root_folder !== null ) {
				$root_folder = $root_folder ?? 0;
				$files_table = ShareBox::getInstance()->frontFilesService->getFilesTable( $root_folder );
				$data        = [
					'files_table' => $files_table,
					'root_folder' => $root_folder,
					'view_logout' => ShareBox::getInstance()->getSettings()->use_password
				];
				$content     = \Craft::$app->view->renderTemplate( 'convergine-sharebox/_frontend/files.twig', $data );
			}
		}
		return $content;

	}

	public function checkLogin( $post ) {
		$response = [ 'res' => false, 'msg' => \Craft::t( 'convergine-sharebox','Incorrect password') ];
		$password = $post['password'] ?? '';
		$token    = $post['token'] ?? '';

		$settings = ShareBox::getInstance()->getSettings();
		if ( $settings->enable_recaptcha ) {
			$resCaptcha = $this->_checkCaptcha( $token, $settings->recaptcha_secret );
			if ( $resCaptcha === false ) {
				$response['msg'] = \Craft::t( 'convergine-sharebox','Captcha error');

				return $response;
			}
		}

		$current_password = $settings->front_password;
		if ( hash( 'sha256', trim( $password ) ) == $current_password ) {
			$response['res'] = true;
			$response['msg'] = \Craft::t( 'convergine-sharebox','Login successful');
            ShareBox::getInstance()->frontFilesService->addStat( 'login' );
		}

		return $response;
	}

	private function _checkCaptcha( $token, $secret ) {
		$base   = "https://www.google.com/recaptcha/api/siteverify";
		$params = array(
			'secret'   => $secret,
			'response' => $token
		);

		$client = new Client();

		$response = $client->request( 'POST', $base, [ 'form_params' => $params ] );

		if ( $response->getStatusCode() == 200 ) {
			$json = json_decode( $response->getBody() );
			if ( $json->success ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	private function _validate_request(){
		$request       = \Craft::$app->getRequest();
		$files_service = ShareBox::getInstance()->fileService;
		$fd            = $request->get( 'fd' );
		$ff            = $request->get( 'ff' );
		$res         = true;
		if ( ! empty( $fd ) ) {
			if ( ! $folder_data = $files_service->getFolderByUid( $fd ) ) {
				$res = false;
			}else{
				\Craft::$app->getSession()->set('root_folder_id',$folder_data['id']);
			}
		}
		if ( ! empty( $ff ) ) {
			if ( ! $file_data = $files_service->getFileByUid( $ff ) ) {
				$res = false;
			}else{
				\Craft::$app->getSession()->set('file_id',$file_data['id']);
			}
		}
		if ( !$res ) {
			\Craft::$app->session->setFlash( 'errors', \Craft::t( 'convergine-sharebox','Incorrect link provided') );
			\Craft::$app->getSession()->setError( \Craft::t( 'convergine-sharebox','Incorrect link provided') );
		}
		return $res;
	}

	public function getHTML() {
		$validate_request = $this->_validate_request();
		$content = $this->_getContent();
		if ( ! $this->isLoggedIn() ) {
			$content = $this->_getLoginForm();
		}
		$data = [
			'content'=>$content,
			'f_error'=>!$validate_request
		];
		return \Craft::$app->view->renderTemplate( 'convergine-sharebox/_frontend/content.twig', $data );
	}

	public function isLoggedIn() {
		$settings     = ShareBox::getInstance()->getSettings();
		$is_logged_in = \Craft::$app->getSession()->get( 'logged_in' );
		if ( $settings->use_password && ! $is_logged_in ) {
			return false;
		}

		return true;
	}

	public function logout() {
		\Craft::$app->getSession()->remove( 'logged_in' );
		\Craft::$app->getSession()->remove( 'root_folder_id' );
		\Craft::$app->getSession()->remove( 'file_id' );
	}

}