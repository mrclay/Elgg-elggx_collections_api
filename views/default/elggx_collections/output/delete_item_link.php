<?php
/**
 * Draw a link that would remove an item from a collection.
 *
 * @uses $vars['collection'] : the Elggx_Collections_Collection object you want to affect
 * @uses $vars['item_guid'] : the GUID you want to remove
 *
 * The rest of the $vars are passed to the output/url view.
 */

$coll = $vars['collection'];
/* @var Elggx_Collections_Collection $coll */

$vars['href'] = "action/elggx_collections/delete_item?" . http_build_query(array(
	'coll_entity_guid' => $coll->getEntityGuid(),
	'coll_name' => $coll->getName(),
	'item_guid' => $vars['item_guid'],
));

if (empty($vars['text'])) {
	$vars['text'] = elgg_echo('elggx_collections:link:delete_item');
}

$vars['is_action'] = true;

echo elgg_view('output/confirmlink', $vars);