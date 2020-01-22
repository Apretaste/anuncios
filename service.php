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
		$ads = Database::query("
			SELECT id, title, subtitle, icon, ROUND((clicks * 100) / impressions) AS ctr
			FROM ads 
			WHERE active = 1
			AND expires >= CURRENT_TIMESTAMP
			ORDER BY ((clicks * 100) / impressions) DESC
			LIMIT 10");

		// add images to the response
		$images = [];
		foreach ($ads as $ad) {
			$images[] = SHARED_PATH . 'ads/' . $ad->icon;
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
		// get the ID
		$id = $request->input->data->id;

		// get the ad
		$ad = Ad::getById($id);

		// do not continue if ad cannot be found
		if (empty($ad)) {
			$response->setCache();
			return $response->setTemplate('message.ejs', [
				"header" => "Anuncio no encontrado",
				"icon" => "sentiment_very_dissatisfied",
				"text" => "Lamentablemente, el anuncio que buscas ha expirado o no existe en nuestro sistema. Revisa la lista de anuncios y escoge otro.",
				"button" => ["href" => "ADS LIST", "caption" => "Ver anuncios"]]);
		}

		// keep only important properties
		$props = ['title','description','image','link','caption','author'];
		foreach ($ad as $key=>$val) {
			if(!in_array($key, $props)) {
				unset($ad->$key);
			}
		}

		// make break lines into <br> for the desc
		$ad->description = nl2br($ad->description);

		// increate the ad's clicks
		Database::query("UPDATE ads SET clicks=clicks+1 WHERE id=$id");

		// create the content for the view
		$content = [
			'isEmail' => $request->input->method == 'email',
			'ad' => $ad];

		// get image for the view
		$image = [];
		if($ad->image) {
			$image[] = SHARED_PATH . 'ads/' . $ad->image;
		}

		// send data to the view
		$response->setCache();
		$response->setTemplate('view.ejs', $content, $image);
	}
}