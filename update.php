<?php
namespace Picnat\Clicnat;

use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_liste_especes;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_indice_qualite;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_sans_tag_invalide;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_pas_prosp_neg;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_interval_date;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_reseau;
use Picnat\Clicnat\ExtractionsConditions\bobs_ext_c_taxon_branche;

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

function dump(bobs_extractions $extraction, $path, $filtre_hivernage=false, $enlever_obs_hivers=true) {
	$pas = 5000;
	$srid = 2154;
	$selection = new bobs_selection(get_db(), ID_SELECTION);
	$selection->vider();
	$extraction->dans_selection($selection->id_selection);

	$filtre = new bobs_selection_retirer_polygon_superficie_max_espece(get_db());
	$filtre->set('id_selection', ID_SELECTION);
	$filtre->prepare();
	$filtre->execute();
	$selection = new bobs_selection(get_db(), ID_SELECTION);

	if ($filtre_hivernage) {
		$as = new bobs_selection_enlever_ou_conserver_que_hivernage(get_db());
		$as->set('id_selection', ID_SELECTION);
		$as->set('enlever', $enlever_obs_hivers);
		$as->prepare();
		$as->execute();
	}

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

//chiros
$reseau = 'cs';
$extraction = new bobs_extractions($db);
$extraction->ajouter_condition(new bobs_ext_c_reseau(get_bobs_reseau($db, $reseau)));
$extraction->ajouter_condition(new bobs_ext_c_indice_qualite(['3','4']));
$extraction->ajouter_condition(new bobs_ext_c_sans_tag_invalide());
$extraction->ajouter_condition(new bobs_ext_c_pas_prosp_neg());
$extraction->ajouter_condition(new bobs_ext_c_interval_date("01/01/2008","31/12/2020"));
dump($extraction, "www/data/cs_ete.geojson", true, true);

$extraction = new bobs_extractions($db);
$extraction->ajouter_condition(new bobs_ext_c_reseau(get_bobs_reseau($db, $reseau)));
$extraction->ajouter_condition(new bobs_ext_c_indice_qualite(['3','4']));
$extraction->ajouter_condition(new bobs_ext_c_sans_tag_invalide());
$extraction->ajouter_condition(new bobs_ext_c_pas_prosp_neg());
$extraction->ajouter_condition(new bobs_ext_c_interval_date("01/01/2008","31/12/2020"));
dump($extraction, "www/data/cs_hivers.geojson", true, false);

$amphibiens = 4088;
$extraction = new bobs_extractions($db);
$extraction->ajouter_condition(new bobs_ext_c_taxon_branche($amphibiens));
$extraction->ajouter_condition(new bobs_ext_c_indice_qualite(['3','4']));
$extraction->ajouter_condition(new bobs_ext_c_sans_tag_invalide());
$extraction->ajouter_condition(new bobs_ext_c_pas_prosp_neg());
$extraction->ajouter_condition(new bobs_ext_c_interval_date("01/01/2010","31/12/2020"));
dump($extraction, "www/data/amphibiens.geojson");

$reptiles = 5277;
$extraction = new bobs_extractions($db);
$extraction->ajouter_condition(new bobs_ext_c_taxon_branche($reptiles));
$extraction->ajouter_condition(new bobs_ext_c_indice_qualite(['3','4']));
$extraction->ajouter_condition(new bobs_ext_c_sans_tag_invalide());
$extraction->ajouter_condition(new bobs_ext_c_pas_prosp_neg());
$extraction->ajouter_condition(new bobs_ext_c_interval_date("01/01/2010","31/12/2020"));
dump($extraction, "www/data/reptiles.geojson");

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
