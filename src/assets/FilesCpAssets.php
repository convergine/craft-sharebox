<?php
namespace convergine\sharebox\assets;

use craft\web\AssetBundle;
use yii\web\JqueryAsset;

class FilesCpAssets extends AssetBundle{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->sourcePath = __DIR__;

		$this->js = [
			'//cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js',
			'js/admin.js',
			'//unpkg.com/dropzone@5/dist/min/dropzone.min.js',
			'//cdn.jsdelivr.net/npm/sweetalert2@11'
		];

		$this->depends = [
			JqueryAsset::class,
		];

		$this->css = [
			'//cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css',
			'css/backend.css',
			'//unpkg.com/dropzone@5/dist/min/dropzone.min.css'
		];

		parent::init();
	}
}