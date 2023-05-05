<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\services;

use craft\base\Component;
use craft\db\Query;

class AnalyticsService extends Component {

	/**
	 * @param $date_from
	 * @param $date_to
	 *
	 * @return string
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \yii\base\Exception
	 */
	public function getAnalyticsTable($date_from,$date_to){

		$data = [];
		$data['downloads'] = $this->getDownloads($date_from,$date_to);
		$data['logins'] = $this->getLogins($date_from,$date_to);

		return \Craft::$app->view->renderTemplate('convergine-sharebox/_analytics/table.twig',$data);
	}

	/**
	 * @param $date_from
	 * @param $date_to
	 *
	 * @return mixed
	 */
	public function getDownloads($date_from,$date_to){

		$res = (new Query())->select( [ 'COUNT(id) as count' ] )
		                     ->from( '{{%conv_stat}}' )
		                     ->where("dateCreated > '{$date_from} 00:00:00' AND
								 dateCreated <= '{$date_to} 23:55:00' AND action='file_download'")->one();
		return $res['count'];
	}

	/**
	 * @param $date_from
	 * @param $date_to
	 *
	 * @return mixed
	 */
	public function getLogins($date_from,$date_to){

		$res = (new Query())->select( [ 'COUNT(id) as count' ] )
		                   ->from( '{{%conv_stat}}' )
		                   ->where("dateCreated > '{$date_from} 00:00:00' AND
								 dateCreated <= '{$date_to} 23:55:00' AND action='login'")->one();
		return $res['count'];
	}
}