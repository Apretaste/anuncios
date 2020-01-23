<?php

use Apretaste\Ad;
use Apretaste\Request;
use Apretaste\Response;
use Framework\Database;

class Service
{
	/**
	 * Service starting point
	 *
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response &$response)
	{
		return $this->_list($request, $response);
	}

	/**
	 * Show the ads with better CTR
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	public function _list(Request $request, Response &$response)
	{
		// get the ads with better CTR
		$where = Ad::getFilters($request->person);
		$ads = Database::query("
			SELECT id, title, subtitle, icon, ROUND((clicks * 100) / impressions) AS ctr
			FROM ads 
			$where
			ORDER BY ((clicks * 100) / impressions) DESC
			LIMIT 10");

		// add images to the response
		$images = [];
		foreach ($ads as $ad) {
			if($ad->icon) {
				$images[] = IMG_PATH . 'ads/' . $ad->icon;
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
	public function _view(Request $request, Response &$response)
	{
		// get the ad's id
		$id = $request->input->data->id;

		// get ad by id
		$where = Ad::getFilters($request->person);
		$ad = Database::query("
			SELECT title, description, image, link, caption, author 
			FROM ads 
			$where
			AND id = $id");

		// do not continue if ad cannot be found
		if (empty($ad)) {
			$response->setCache();
			return $response->setTemplate('message.ejs', [
				"header" => "Anuncio no encontrado",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "Lamentablemente, el anuncio que buscas ha expirado o no existe en nuestro sistema. Revisa la lista de anuncios y escoge otro.",
				"button" => ["href" => "ADS LIST", "caption" => "Ver anuncios"]]);
		}

		// increate the ad's clicks
		Database::query("UPDATE ads SET clicks=clicks+1 WHERE id=$id");

		// create performance report
		$today = Date('Y-m-d');
		Database::query("
			INSERT INTO ads_performance (inserted, ad_id, person_id, clicks) 
			VALUES ('$today', $id, {$request->person->id}, 1)
			ON DUPLICATE KEY UPDATE clicks=clicks+1");

		// create demographics report
		Database::query("
			INSERT INTO ads_report (ad_id, person_id, method, os_type, gender, age, province, education) 
			VALUES ($id, {$request->person->id}, '{$request->input->method}', '{$request->input->ostype}', 
			NULLIF('{$request->person->gender}', ''), NULLIF('{$request->person->age}', ''), 
			NULLIF('{$request->person->provinceCode}', ''), NULLIF('{$request->person->education}', ''))");

		// make the description into HTML
		$ad[0]->description = nl2br($ad[0]->description);

		// create the content for the view
		$content = [
			'isEmail' => $request->input->method == 'email',
			'ad' => $ad[0]];

		// get image for the view
		$image = $ad[0]->image ? [IMG_PATH . 'ads/' . $ad[0]->image] : [];

		// send data to the view
		$response->setCache();
		$response->setTemplate('view.ejs', $content, $image);
	}
}