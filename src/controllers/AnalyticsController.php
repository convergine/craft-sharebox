<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\controllers;

use convergine\sharebox\ShareBox;
use craft\web\Controller;


class AnalyticsController extends Controller
{
	// Public Methods
	// =========================================================================

	public function actionIndex() {

		$date_from = date('Y-m-d',strtotime("-1 month"));
		$date_to = date('Y-m-d');
		$variables = [
			'title' => 'Statistics',
			'table' => ShareBox::getInstance()->analyticsService->getAnalyticsTable($date_from,$date_to),
			'date_from'=>date('m/d/Y',strtotime("-1 month")),
			'date_to'=>date('m/d/Y')
		];

		return $this->renderTemplate('convergine-sharebox/_analytics/index', $variables);
	}


	public function actionGetTable(){

		$this->requirePostRequest();
		$request = \Craft::$app->getRequest();
		$post = $request->post();
		$analytics_service = ShareBox::getInstance()->analyticsService;
		$date_from = \DateTime::createFromFormat("m/d/Y",$post['date_from']['date']);
		$date_to= \DateTime::createFromFormat("m/d/Y",$post['date_to']['date']);
		if(!$date_from || !$date_to){
			return $this->asJson(['res'=>false,'msg'=>'Incorrect date entered']);
		}
		$data=[
			'res'=>true,
			'table'=>$analytics_service->getAnalyticsTable($date_from->format("Y-m-d"),$date_to->format("Y-m-d")),
			'date_from'=>$date_from->format("Y-m-d"),
			'date_to'=>$date_to->format("Y-m-d"),
		];
		return $this->asJson($data);
	}



}
