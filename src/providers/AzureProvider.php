<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\providers;

use convergine\sharebox\services\FileProviderInterface;
use convergine\sharebox\ShareBox;
use convergine\sharebox\services\FileService;
use craft\db\Query;
use craft\helpers\App;
use craft\web\UploadedFile;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureProvider extends FileService implements FileProviderInterface
{
	/**
	 * @return false|string
	 */
	private function _getConnectionString(){
		$blobStorageKey = App::env('AZURE_BLOB_STORAGE_KEY');
		$blobStorageAccountName = App::env('AZURE_BLOB_STORAGE_ACCOUNT_NAME');
		return $blobStorageKey !== null && $blobStorageAccountName !== null?
			"DefaultEndpointsProtocol=https;AccountName={$blobStorageAccountName};AccountKey={$blobStorageKey};EndpointSuffix=core.windows.net":
			false;
	}

	/**
	 * @return string|bool
	 */
	private function _getContainer(){

		return App::env('AZURE_CONTAINER') ?? false;
	}

	public function index(){
		echo "AzureProvider";
	}

	/**
	 * @inheritdoc
	 */
	public function credentialsIsSet():bool{
		return $this->_getConnectionString() && $this->_getContainer();
	}

	/**
	 * @inheritdoc
	 */
	public function getNoticeTemplatePath():string{
		return 'convergine-sharebox/_notices/azure';
	}

	/**
	 * @inheritdoc
	 */
	public function getFileContent($uid,$count=false):array{
		$file_data = $this->getFileByUid($uid);
		if(!$file_data){
			return \Craft::t( 'convergine-sharebox','File no found');
		}

		$blobClient = BlobRestProxy::createBlobService($this->_getConnectionString());
		try{
			$blob = $blobClient->getBlob($this->_getContainer(), $file_data['path']);
			$binary_stream = $blob->getContentStream();
			if($count){
				\Craft::$app->db->createCommand()->update( '{{%conv_files}}', [
					'downloaded'      => $file_data['downloaded']+1
				],[
					'id'=>$file_data['id']
				] )->execute();
				$description = $file_data['path'];
                ShareBox::getInstance()->frontFilesService->addStat('file_download',$description,$file_data['id']);
			}
			return [
				'name'=>$file_data['name'],
				'size'=>$file_data['size'],
				'stream'=>$binary_stream,
				'mime'=>$file_data['mime']
			];
		}
		catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here:
			// http://msdn.microsoft.com/library/azure/dd179439.aspx
			$error_message = $e->getMessage();
			return $error_message;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function saveFile():array{
		$response = ['res'=>false,'msg'=>'','file_id'=>0];
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$files =  UploadedFile::getInstanceByName('file');
		$folder_id = $post['folder_id'];
		$size = $files->size;
		$file_name = $files->name;
		$file_mime = $files->type;
		$file_path = $this->getFolderPath($folder_id).$file_name;

		$fileClient = BlobRestProxy::createBlobService($this->_getConnectionString());
		$fileContent = file_get_contents($files->tempName);
		try	{
			//MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions
			$options = new CreateBlockBlobOptions();
			$options->setContentType($files->type);
			//Upload blob
			$fileClient->createBlockBlob($this->_getContainer(), $file_path, $fileContent,$options);

			//Check file already exist
			$file = (new Query())->select( [ '*' ] )
			                     ->from( '{{%conv_files}}' )
			                     ->where(['path'=>$file_path])
			                     ->one();
			if($file){
				\Craft::$app->db->createCommand()->update( '{{%conv_files}}', [
					'name'      => $file_name,
					'path'      => $file_path,
					'folder_id' => $folder_id,
					'size'      => $size,
					'mime'=>$file_mime
				],[
					'id'=>$file['id']
				] )->execute();
				$response['file_id'] = $file['id'];
			}else{
				\Craft::$app->db->createCommand()->insert( '{{%conv_files}}', [
					'name'      => $file_name,
					'path'      => $file_path,
					'folder_id' => $folder_id,
					'size'      => $size,
					'mime'=>$file_mime
				] )->execute();
				$response['file_id'] = \Craft::$app->db->getLastInsertID();
			}

			$response['res'] = true;
			return $response;
		}
		catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here:
			// http://msdn.microsoft.com/library/azure/dd179439.aspx
			$error_message = $e->getMessage();
			$response['msg'] = $error_message;
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
		$fileClient = BlobRestProxy::createBlobService($this->_getConnectionString());
		$blobOptions = new ListBlobsOptions();
		$blobOptions->setPrefix($folder_path);

		try {
			$filesList = $fileClient->listBlobs($this->_getContainer(),$blobOptions);

			$files=$filesList->getBlobs();

			foreach ($files as $file){
				$fileClient->deleteBlob($this->_getContainer(),$file->getName());
			}


			$this->removeFoldersFiles($id_folder);
			$response['res']=true;
			$response['msg']=\Craft::t( 'convergine-sharebox','Folder removed');
		} catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here:
			// http://msdn.microsoft.com/library/azure/dd179439.aspx
			$code = $e->getCode();
			$error_message = $e->getMessage();
			$response['msg'] = $error_message;
		}
		return $response;
	}

	/**
	 * @inheritdoc
	 */
	public function removeFile($post):array{
		$response = ['res'=>false,'msg'=>'','folder_id'=>0];
		$file_id = $post['file_id']??0;
		$file_data = $this->getFile($file_id);
		if(!$file_data){
			$response['msg']=\Craft::t( 'convergine-sharebox','File not found');
			return $response;
		}
		$response['folder_id'] = $file_data['folder_id'];

		$fileClient = BlobRestProxy::createBlobService($this->_getConnectionString());

		try {
			$fileClient->deleteBlob($this->_getContainer(),$file_data['path']);

			\Craft::$app->db->createCommand()->delete('{{%conv_files}}',[
				'id'=> $file_data['id']
			])->execute();

			$response['res']=true;
			$response['msg']=\Craft::t( 'convergine-sharebox','File removed');
		} catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here:
			// http://msdn.microsoft.com/library/azure/dd179439.aspx

			$error_message = $e->getMessage();
			$response['msg'] = $error_message;
		}
		return $response;
	}

	/**
	 * @inheritdoc
	 */
	public function moveBlob($file_id,$from,$to):bool{

		$fileClient = BlobRestProxy::createBlobService($this->_getConnectionString());

		if($from==$to){
			return true;
		}

		try {
			$fileClient->copyBlob(
				$this->_getContainer(),
				$to,
				$this->_getContainer(),
				$from
			);

			$fileClient->deleteBlob( $this->_getContainer(), $from );

			 \Craft::$app->db->createCommand()->update( '{{%conv_files}}', [
				'path'      => $to
			],['id'=>$file_id] )->execute();

		} catch ( ServiceException $e ) {
			// Handle exception based on error codes and messages.
			// Error codes and messages are here:
			// http://msdn.microsoft.com/library/azure/dd179439.aspx
			return false;
		}
		return true;
	}
}
