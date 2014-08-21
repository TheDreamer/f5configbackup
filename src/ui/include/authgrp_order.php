<?php

// Get params
$direction = current($_GET["order"]);
$id = key($_GET["order"]);

// check ID 
if (! is_numeric($id) ) {
	header("Location: /authgrp.php"); 
	die;
};

// Build ID-ORD array
foreach ($groups as $i) {
	$id_ord[ $i['ID']] = $i['ORD'];
};

// Build ORD-ID array
$ord_id = array_flip($id_ord);

// Dont allow invalid order change, 
// first cant be moved up, last cant move down
$first = current($id_ord);
$last = end($id_ord);
if ( ($direction == 'up' && $id_ord[$id] == $first) ||
		($direction == 'down' && $id_ord[$id] == $last) ) {
	header("Location: /authgrp.php"); 
	die;
};

// Which direction are we moving the group? 
// Get the new order number for group
switch ( $direction ) {
	case "up" :
		$new_ord = $id_ord[$id] - 1;
		break;
	case "down" :
		$new_ord = $id_ord[$id] + 1;
		break;
	default:
		// if none, redirect to devices page
		header("Location: /authgrp.php"); 
		die;
};

$old_ord = $id_ord[$id]; // What was the original order number?
$old_device = $ord_id[$new_ord]; // What device has the new ord number ?

// Update order
$dbh->beginTransaction();
try {
	// Update order for the group we selected
	$sth = $dbh->prepare("UPDATE AUTHGROUPS SET ORD = ? WHERE ID = ?");
	$sth->bindParam(1,$new_ord); 
	$sth->bindParam(2,$id); 
	$sth->execute();

	// Swap group that has the new order # with our group's old order #
	$sth = $dbh->prepare("UPDATE AUTHGROUPS SET ORD = ? WHERE ID = ?");
	$sth->bindParam(1,$old_ord); 
	$sth->bindParam(2,$old_device); 
	$sth->execute();

	// Completed 
	$dbh->commit();
	header("Location: /authgrp.php"); 
	die;
} catch (Exception $e) {
	$dbh->rollBack();
	$contents = '<p class="error">Error: '.$e->getMessage().'</p>';
};
$title = "Change Order";

?>