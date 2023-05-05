<?php
namespace convergine\sharebox\models;

use craft\base\Model;


class SettingsModel extends Model
{

	/**
	 * @var string - page URL, where files table presented
	 */
	public $files_page_url = '';

	/**
	 * @var bool - enable reCaptcha
	 */
	public $enable_recaptcha;

	/**
	 * @var string - reCaptcha site key
	 */
	public $recaptcha_site = '';

	/**
	 * @var string - reCaptcha secret key
	 */
	public $recaptcha_secret = '';

	/**
	 * @var bool - enable password protection on frontend
	 */
	public $use_password ;

	/**
	 * @var string - frontend password
	 */
	public $front_password = '';

	public $confirm_front_password = '';

	/**
	 * @var string - track last password changed date
	 */
	public $password_changed_at;


	/**
	 * @inheritdoc
	 */
	protected function defineRules(): array
	{
		$rules = parent::defineRules();
		$rules[] = [['files_page_url'], 'required'];

		$rules[] = [['front_password'],'string', 'min'=>6];
		$rules[] = [['front_password'],'match', 'pattern' => '/^.*(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/',
		             'message' => \Craft::t('convergine-sharebox', 'Password must contain at least one lower and upper case character and a digit.')];

		$rules[] = [['confirm_front_password'], 'required', 'when' => function($model) {
			return $model->front_password !='' && $model->use_password;
		}];
		$rules[] = [['confirm_front_password'], 'compare', 'compareAttribute' => 'front_password'];

		$rules[] = [['recaptcha_site'], 'required',
            'message' => \Craft::t('convergine-sharebox', 'Recaptcha Site Key cannot be blank'), 'when' => function($model) {
			return $model->enable_recaptcha;
		}];
		$rules[] = [['recaptcha_secret'], 'required',
            'message' => \Craft::t('convergine-sharebox', 'Recaptcha Secret Key cannot be blank'), 'when' => function($model) {
			return $model->enable_recaptcha;
		}];

		return $rules;
	}
}


