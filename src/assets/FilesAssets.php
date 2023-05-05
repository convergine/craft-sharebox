<?php
namespace convergine\sharebox\assets;

use craft\web\AssetBundle;
use yii\web\JqueryAsset;

class FilesAssets extends AssetBundle{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->sourcePath = __DIR__;

		$this->js = [

			'//cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js',
			'//cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js',
			'js/frontend.js',
		];

		$this->depends = [
			JqueryAsset::class,
		];

		$this->css = [
			'css/frontend.css',
			'//cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css',
			'//cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css'
		];

		parent::init();
	}
}