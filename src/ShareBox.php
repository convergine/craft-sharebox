<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Developer   https://github.com/denisPiskun
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    craft.cms
 *
 * Copyright:   (c) 2009 - 2020  Convergine.com
 *
 */
namespace convergine\sharebox;

use convergine\sharebox\models\SettingsModel;
use convergine\sharebox\providers\AmazonS3Provider;
use convergine\sharebox\providers\AzureProvider;
use convergine\sharebox\services\FileService;
use convergine\sharebox\services\FrontendService;
use convergine\sharebox\services\FrontFilesService;
use convergine\sharebox\variables\AzureFilesVariable;
use convergine\sharebox\assets\FilesCpAssets;
use Craft;
use craft\base\Model;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

/**
 * Class ShareBox
 *
 * @property FileService $fileService
 * @property AzureProvider $azureProvider
 * @property AmazonS3Provider $amazonProvider
 * @property FrontFilesService $frontFilesService
 * @property FrontendService $frontendService
 */

class ShareBox extends \craft\base\Plugin
{
	public bool $hasCpSettings = true;

	public bool $hasCpSection = true;

	public ?string $name = 'ShareBox';

	public ?string $currentPassword;

	public function init()
	{
		parent::init();
		/** @var SettingsModel $settings */
		$settings = $this->getSettings();
		$this->currentPassword = $settings->front_password;


		// Register CP routes
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
				$event->rules['convergine-sharebox'] = 'convergine-sharebox/files/index';
				$event->rules['convergine-sharebox/files'] = 'convergine-sharebox/files/index';
				$event->rules['convergine-sharebox/analytics'] = 'convergine-sharebox/analytics/index';
				$event->rules['convergine-sharebox/settings'] = 'convergine-sharebox/settings/index';
			}
		);
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_SITE_URL_RULES,
			function (RegisterUrlRulesEvent $event) {

				$event->rules['convergine-sharebox/frontend/download/<id:[\w-]+>/<file>'] = 'convergine-sharebox/frontend/download';

			}
		);

		// Register components
		$this->setComponents([
			'fileService'   => \convergine\sharebox\services\FileService::class,
			'frontendService' =>\convergine\sharebox\services\FrontendService::class,
			'frontFilesService'   => \convergine\sharebox\services\FrontFilesService::class,
			'analyticsService'   => \convergine\sharebox\services\AnalyticsService::class,
		]);

		// Register providers
		$this->setComponents([
			'azureProvider'   => \convergine\sharebox\providers\AzureProvider::class,
			'amazonProvider'   => \convergine\sharebox\providers\AmazonS3Provider::class,
		]);

		// Register variables
		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function (Event $event) {
				$variable = $event->sender;
				$variable->set('azurefiles', AzureFilesVariable::class);
			}
		);
		Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, function (RegisterTemplateRootsEvent $event) {
			$event->roots[$this->id] = __DIR__ . '/templates';
		});

        // Register translations
        $this->registerShareBoxTranslations();

        // Register assets for backend
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(FilesCpAssets::class);
        }
    }

	public function getCpNavItem(): ?array
	{

		$ret          = parent::getCpNavItem();
		$ret['label'] = 'ShareBox';

		$ret['subnav']['files'] = [
			'label' => 'Files',
			'url'   => 'convergine-sharebox/files',
		];
		$ret['subnav']['analytics'] = [
			'label' => 'Statistics',
			'url'   => 'convergine-sharebox/analytics',
		];

		$ret['subnav']['settings'] = [
			'label' => 'Settings',
			'url'   => 'convergine-sharebox/settings',
		];
		return $ret;
	}

	protected function createSettingsModel(): ?Model
	{
		return new SettingsModel();
	}

	public function getSettingsResponse(): mixed
	{
		return \Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('convergine-sharebox/settings'));
	}

	protected function settingsHtml(): ?string
	{
		return \Craft::$app->getView()->renderTemplate(
			'convergine-sharebox/settings',
			[ 'settings' => $this->getSettings() ]
		);
	}

	public function beforeSaveSettings(): bool
	{
		$request = \Craft::$app->request->post();
		$attributes = $this->getSettings()->getAttributes();
		if(empty($request['settings']['front_password'])){
			$attributes['front_password']=$this->currentPassword;
			$this->getSettings()->setAttributes($attributes,false);
			return true;
		}else{
			$attributes['front_password']= hash('sha256',$request['settings']['front_password']);
		}
		$date = DateTimeHelper::now();
		$attributes['password_changed_at']=$date->format('Y-m-d H:i:s');
		$this->getSettings()->setAttributes($attributes, false);
		return true;
	}

	function getProvider(){
		$provider_str = App::env( 'SHAREBOX_PROVIDER' );
		if(!$provider_str){
			return false;
		}elseif ($provider_str == 'AZUREBLOB'){
			return $this->azureProvider;
		}elseif ($provider_str == 'AMAZONS3'){
			return $this->amazonProvider;
		}

		return false;
	}

    protected function registerShareBoxTranslations()
    {
        Craft::$app->i18n->translations['convergine-sharebox'] = [
            'class' => \yii\i18n\PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => '@convergine/sharebox/translations',
            'forceTranslation' => true,
        ];
    }

}
