<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\providers;

use Aws\Credentials\CredentialProvider;
use Aws\S3\Exception\S3Exception;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;
use convergine\sharebox\services\FileProviderInterface;
use convergine\sharebox\ShareBox;
use convergine\sharebox\services\FileService;
use craft\db\Query;
use craft\helpers\App;
use craft\web\UploadedFile;

class AmazonS3Provider extends FileService  implements FileProviderInterface{

	/**
	 * @return false|string
	 */
	private function _getRegion() {
		return App::env( 'AWS_REGION' ) ?? false;
	}

	/**
	 * @return false|string
	 */
	private function _getBucket() {
		return App::env( 'AWS_BUCKET' ) ?? false;
	}

	/**
	 * @inheritdoc
	 */
	public function credentialsIsSet():bool {
		$aws_access_key = App::env( 'AWS_ACCESS_KEY_ID' );
		$aws_secret_key = App::env( 'AWS_SECRET_ACCESS_KEY' );

		return $this->_getRegion() && $this->_getBucket() && $aws_access_key && $aws_secret_key;
	}

	public function getNoticeTemplatePath():string {
		return 'convergine-sharebox/_notices/amazon';
	}

	/**
	 * @inheritdoc
	 */
	public function saveFile():array {
		$response = [ 'res' => false, 'msg' => '', 'file_id' => 0 ];
		$request  = \Craft::$app->getRequest();
		$post     = $request->post();
		$files    = UploadedFile::getInstanceByName( 'file' );

		$folder_id = $post['folder_id'];
		$size      = $files->size;
		$file_name = $files->name;
		$file_mime = $files->type;
		$file_path = $this->getFolderPath( $folder_id ) . $file_name;

		$client      = new S3Client( [
			'region'      => $this->_getRegion(),
			'version'     => '2006-03-01',
			'credentials' => CredentialProvider::env()
		] );
		$file_stream = fopen($files->tempName, 'rb');
		try {

			//Upload Bucket
			$uploader = new ObjectUploader(
				$client,
				$this->_getBucket(),
				$file_path,
				$file_stream
			);

			do {
				try {
					$result = $uploader->upload();
				} catch (MultipartUploadException $e) {
					rewind($file_stream);
					$uploader = new MultipartUploader($client, $file_stream, [
						'state' => $e->getState(),
					]);
				}
			} while (!isset($result));
			fclose($file_stream);

			//Check file already exist
			$file = ( new Query() )->select( [ '*' ] )
			                       ->from( '{{%conv_files}}' )
			                       ->where( [ 'path' => $file_path ] )
			                       ->one();
			if ( $file ) {
				\Craft::$app->db->createCommand()
				                ->update( '{{%conv_files}}', [
					                'name'      => $file_name,
					                'path'      => $file_path,
					                'folder_id' => $folder_id,
					                'size'      => $size,
					                'mime'      => $file_mime
				                ], [
					                'id' => $file['id']
				                ] )->execute();
				$response['file_id'] = $file['id'];
			} else {
				\Craft::$app->db->createCommand()
				                ->insert( '{{%conv_files}}', [
					                'name'      => $file_name,
					                'path'      => $file_path,
					                'folder_id' => $folder_id,
					                'size'      => $size,
					                'mime'      => $file_mime
				                ] )->execute();
				$response['file_id'] = \Craft::$app->db->getLastInsertID();
			}

			$response['res'] = true;

			return $response;
		} catch ( S3Exception $e ) {
			$response['msg'] = $e->getMessage();
		}

		return $response;
	}

	/**
	 * @inheritdoc
	 */
	public function getFileContent( $uid, $count = false ):array {
		$file_data = $this->getFileByUid( $uid );
		if ( ! $file_data ) {
			return \Craft::t( 'convergine-sharebox', 'File not found' );
		}
		$client = new S3Client( [
			'region'      => $this->_getRegion(),
			'version'     => '2006-03-01',
			'credentials' => CredentialProvider::env()
		] );

		// Register the stream wrapper from an S3Client object
		$client->registerStreamWrapper();
		$binary_stream = fopen( 's3://' . $this->_getBucket() . '/' . $file_data['path'], 'rb' );
		if ( $count ) {
			\Craft::$app->db->createCommand()->update( '{{%conv_files}}', [
				'downloaded' => $file_data['downloaded'] + 1
			], [
				'id' => $file_data['id']
			] )->execute();
			$description = $file_data['path'];
            ShareBox::getInstance()->frontFilesService->addStat( 'file_download', $description,$file_data['id'] );
		}

		return [
			'name'   => $file_data['name'],
			'size'   => $file_data['size'],
			'stream' => $binary_stream,
			'mime'   => $file_data['mime']
		];
	}

	/**
	 * @inheritdoc
	 */
	public function removeFile( $post ):array {

		$response  = [ 'res' => false, 'msg' => '', 'folder_id' => 0 ];
		$file_id   = $post['file_id'] ?? 0;
		$file_data = $this->getFile( $file_id );
		if ( ! $file_data ) {
			$response['msg'] = \Craft::t( 'convergine-sharebox', 'File not found' );

			return $response;
		}
		$response['folder_id'] = $file_data['folder_id'];

		try {
			//Create a S3Client
			$client = new S3Client( [
				'region'  => $this->_getRegion(),
				'version' => '2006-03-01',
				'credentials' => CredentialProvider::env()
			] );

			$client->deleteObject( [
				'Bucket' => $this->_getBucket(),
				'Key'    => $file_data['path'],
			] );
			\Craft::$app->db->createCommand()->delete( '{{%conv_files}}', [
				'id' => $file_data['id']
			] )->execute();

			$response['res'] = true;
			$response['msg'] = \Craft::t( 'convergine-sharebox', 'File removed' );
		} catch ( S3Exception $e ) {
			$response['msg'] = $e->getMessage();
		}

		return $response;
	}

	/**
	 * @inheritdoc
	 */
	public function removeFolder($post):array{

		$response = ['res'=>false,'msg'=>'','parent_id'=>0];
		$id_folder = $post['folder_id']??0;

		$folder_data = $this->getFolder($id_folder);
		if(!$folder_data){
			$response['msg'] = \Craft::t('convergine-sharebox','Folder not found');
			return $response;
		}
		$response['parent_id'] = $folder_data['parent_id'];
		$folder_path = $this->getFolderPath($id_folder);

		$client = new S3Client( [
			'region'      => $this->_getRegion(),
			'version'     => '2006-03-01',
			'credentials' => CredentialProvider::env()
		] );
		$bucket = $this->_getBucket();
		try {

			$results = $client->getPaginator( 'ListObjects', [
				'Bucket' => $bucket,
				'Prefix' => $folder_path,
			] );

			foreach ( $results as $result ) {
				foreach ( $result['Contents'] as $object ) {

					$client->deleteObject( [
						'Bucket' => $bucket,
						'Key'    => $object['Key']
					] );
				}
			}

			$this->removeFoldersFiles( $id_folder );
			$response['res'] = true;
			$response['msg'] = \Craft::t( 'convergine-sharebox', 'Folder removed' );

		} catch ( S3Exception $e ) {
			$response['msg'] = $e->getMessage();
		};

		return $response;
	}

	/**
	 * @inheritdoc
	 */
	public function moveBlob($file_id,$from,$to):bool{

		if($from==$to){

			return true;
		}
		$client = new S3Client( [
			'region'      => $this->_getRegion(),
			'version'     => '2006-03-01',
			'credentials' => CredentialProvider::env()
		] );
		$bucket = $this->_getBucket();
		try {
			$client->copyObject([
				'Bucket'     => $bucket,
				'Key'        => $to,
				'CopySource' => "{$bucket}/{$from}",
			]);
			$client->deleteObject( [
				'Bucket' => $bucket,
				'Key'    => $from
			] );
			\Craft::$app->db->createCommand()->update( '{{%conv_files}}', [
				'path'      => $to
			],['id'=>$file_id] )->execute();

		} catch ( ServiceException $e ) {

			return false;
		}
		return true;
	}
}