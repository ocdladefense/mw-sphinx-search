<?php


class SphinxSearchSampleResponse {

	public static $results = array(

		"error" => "",
		"warning" => "",
		"status" => "",
		"fields" => array(
			"page_title","old_text"
		),
		"attrs" => array(
			'page_namespace' => 1,
			'page_is_redirect' => 1,
			'old_id' => 1,
			'category' => 1073741825
		),
		"matches" => array(
			1115 => array(
				"weight" => 501,
				"attrs" => array(
					"page_namespace" => 0,
					"page_is_redirect" => 1,
					"old_id" => 7542,
					"category" => array(
						1152,2077
					)
				)
			),
			1956 => array()
		),
		"total" => 513,
		"total_found" => 513,
		"time" => "0.001",
		"words" => array(
			"drive" => array(
				"docs" => "560",
				"hits" => "2713"
			)
		)

	);


}