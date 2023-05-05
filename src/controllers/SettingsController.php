<?php
/**
 * Author:     Convergine (http://www.convergine.com)
 * Website:    http://www.convergine.com
 * Support:    http://support.convergine.com
 * Version:    craftfiles.repo
 *
 * Copyright:   (c) 2009 - 2023  Convergine.com
 *
 * Date: 4/25/2023
 * Time: 11:06 AM
 */

namespace convergine\sharebox\controllers;

use convergine\sharebox\ShareBox;
use craft\web\Controller;
use Craft;

class SettingsController extends Controller {
	public function actionIndex()
	{
		$settings = ShareBox::getInstance()->getSettings();

		return $this->renderTemplate('convergine-sharebox/settings.twig', ['settings' => $settings]);
	}

	public function actionSave()
	{
		$this->requirePostRequest();

		$params = Craft::$app->getRequest()->getBodyParams();
		$data = $params['settings'];

		$settings = ShareBox::getInstance()->getSettings();

		$settings->files_page_url         = $data['files_page_url'] ?? '';
		$settings->enable_recaptcha       = $data['enable_recaptcha'] ?? '';
		$settings->recaptcha_site         = $data['recaptcha_site'] ?? '';
		$settings->recaptcha_secret       = $data['recaptcha_secret'] ?? '';
		$settings->use_password           = $data['use_password'] ?? '';
		$settings->front_password         = $data['front_password'] ?? '';
		$settings->confirm_front_password = $data['confirm_front_password'] ?? '';

		if (!$settings->validate()) {
			Craft::$app->getSession()->setError(Craft::t('convergine-sharebox', 'Couldn’t save settings.'));
			return $this->renderTemplate('convergine-sharebox/settings', compact('settings'));
		}

		$pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(ShareBox::getInstance(), $settings->toArray());

		if (!$pluginSettingsSaved) {
			Craft::$app->getSession()->setError(Craft::t('convergine-sharebox', 'Couldn’t save settings.'));
			return $this->renderTemplate('convergine-sharebox/settings', compact('settings'));
		}

		Craft::$app->getSession()->setNotice(Craft::t('convergine-sharebox', 'Settings saved.'));

		return $this->redirectToPostedUrl();
	}
}