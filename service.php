<?php

use Apretaste\Ad;
use Apretaste\Bucket;
use Apretaste\Request;
use Apretaste\Response;
use Apretaste\Database;
use Framework\GoogleAnalytics;

class Service
{
	/**
	 * Service starting point
	 *
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response $response)
	{
		return $this->_list($request, $response);
	}

	/**
	 * Show the ads with better CTR
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _list(Request $request, Response $response)
	{
		// get the ads with better CTR
		$filters = Ad::getFilters($request->person);
		$ads = Database::query("
			SELECT id, title, subtitle, icon
			FROM ads
			WHERE status='ACTIVE'
			$filters
			ORDER BY ((clicks * 100) / impressions) DESC");

		// add images to the response
		$images = [];
		foreach ($ads as $ad) {
			if($ad->icon) {
				$images[] = Bucket::getPathByEnvironment('anuncios', $ad->icon);
			}
		}

		// send data to the view
		$response->setCache();
		$response->setTemplate('list.ejs', ['ads' => $ads], $images);
	}

	/**
	 * Show the answer for an FAQ entry
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _view(Request $request, Response $response)
	{
		// get the ad's id
		$id = $request->input->data->id;

		// get ad by id
		$ad = Ad::find($id);

		// stop if ad cannot be found
		if (empty($ad)) {
			$response->setCache();
			return $response->setTemplate('message.ejs');
		}

		// increate the ad's clicks
		Database::query("UPDATE ads SET clicks=clicks+1 WHERE id=$id");

		// create performance report
		$today = Date('Y-m-d');
		Database::query("
			INSERT INTO ads_performance (inserted, ad_id, clicks)
			VALUES ('$today', $id, 1)
			ON DUPLICATE KEY UPDATE clicks=clicks+1");

		// create demographics report
		Database::query("
			INSERT INTO ads_report (ad_id, person_id, method, os_type, gender, age, province, education) 
			VALUES ($id, {$request->person->id}, NULLIF('{$request->input->method}', ''), NULLIF('{$request->input->osType}', ''),
			NULLIF('{$request->person->gender}', ''), NULLIF('{$request->person->age}', ''), 
			NULLIF('{$request->person->provinceCode}', ''), NULLIF('{$request->person->education}', ''))");

		// submit to Google Analytics 
		GoogleAnalytics::event('ad_open', $ad->title);

		// keep only important properties
		$props = ['image','description','facebook','twitter','instagram','email','phone','gallery','btnLink','btnCaption','btnColor'];
		foreach ($ad as $key=>$val) {
			if(!in_array($key, $props)) {
				unset($ad->$key);
			}
		}

		// get image for the view
		$images = [];
		if($ad->image) {
			$images[] = $ad->image;
			$ad->image = basename($ad->image);
		}

		// add the gallery to the array of images
		for ($i=0; $i < count($ad->gallery); $i++) { 
			$images[] = $ad->gallery[$i]->img;
			$ad->gallery[$i] = basename($ad->gallery[$i]->img);
		}

		// create the content for the view
		$content = [
			'isEmail' => $request->input->method == 'email',
			'ad' => $ad
		];

		// send data to the view
		$response->setCache();
		$response->setTemplate('view.ejs', $content, $images);
	}
}
