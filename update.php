<?php
namespace Picnat\Clicnat;

use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_liste_especes;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_indice_qualite;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_sans_tag_invalide;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_pas_prosp_neg;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_interval_date;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_reseau;

require_once("db.php");
require_once("config.php");
require_once("vendor/autoload.php");

get_config("./config.xml");

define('LISTE_ESPECE_UMAM', 514);
define('ID_SELECTION', 20543);

get_db($db);
function carre($carre, $pas, $n_occurences, $n_species) {
	if (!preg_match("/^E(\d+)N(\d+)$/", $carre, $m)) {
		throw new \Exception("nom du carré match pas la regexp");
	}
	list(,$x,$y) = $m;
	$x = (int)$x*1000;
	$y = (int)$y*1000;
	$coordinates = [
		[$x, $y],
		[$x+$pas, $y],
		[$x+$pas, $y+$pas],
		[$x, $y+$pas],
		[$x, $y]
	];
	return [
		"type" => "Feature",
		"properties" => [
			"id" => $carre,
			"occurences" => (int)$n_occurences,
			"species"    => (int)$n_species
		],
		"geometry" => [
			"type" => "Polygon",
			"coordinates" => [$coordinates]
		]
	];
}

function dump(bobs_extractions $extraction, $path) {
	$pas = 5000;
	$srid = 2154;
	$selection = new bobs_selection(get_db(), ID_SELECTION);
	$selection->vider();
	$extraction->dans_selection($selection->id_selection);
	$n_carres = $selection->carres_nespeces_ncitations($pas,$srid);
	$carto = [
		"type" => "FeatureCollection",
		"features" => [],
		"crs" => [
			"type" => "name",
			"properties" => [
				"name" => "EPSG:2154"
			]
		]
	];

	foreach ($n_carres as $c) {
		$nom = sprintf("E%04dN%04d", ($c['x0']*$pas)/1000, ($c['y0']*$pas)/1000);
		echo "$nom {$c['count_citation']} {$c['count_especes']}\n";
		$feature = carre($nom, $pas, $c['count_citation'], $c['count_especes']);
		$feature['properties']['taxons'] = [];
		foreach ($selection->carre_liste_especes($pas, $srid, $c['x0'], $c['y0']) as $taxon) {
			$feature['properties']['taxons'][] = [
				'id_espece' => $taxon->id_espece,
				'lib' => $taxon->__toString(),
				'nom_s' => $taxon->nom_s
			];
		}
		$carto['features'][] = $feature;
	}

	file_put_contents($path, json_encode($carto, JSON_PRETTY_PRINT));
}

$extraction = new bobs_extractions(get_db());
$extraction->ajouter_condition(new bobs_ext_c_liste_especes(LISTE_ESPECE_UMAM));
$extraction->ajouter_condition(new bobs_ext_c_indice_qualite(['3','4']));
$extraction->ajouter_condition(new bobs_ext_c_sans_tag_invalide());
$extraction->ajouter_condition(new bobs_ext_c_pas_prosp_neg());
$extraction->ajouter_condition(new bobs_ext_c_interval_date("01/01/2010","31/12/2020"));
dump($extraction, "www/data/micro.geojson");

$extraction = new bobs_extractions(get_db());
$extraction->ajouter_condition(new bobs_ext_c_reseau(new clicnat2_reseau(get_db(), 'mt')));
$extraction->ajouter_condition(new bobs_ext_c_indice_qualite(['3','4']));
$extraction->ajouter_condition(new bobs_ext_c_sans_tag_invalide());
$extraction->ajouter_condition(new bobs_ext_c_pas_prosp_neg());
$extraction->ajouter_condition(new bobs_ext_c_interval_date("01/01/2010","31/12/2020"));
dump($extraction, "www/data/mt.geojson");
