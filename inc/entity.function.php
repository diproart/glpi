<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')){
	die("Sorry. You can't access directly to this file");
	}

/**
 * Show users of an entity
 *
 * @param $target string : where to go on action
 * @param $ID integer : entity ID
 */
function showEntityUser($target,$ID){
	global $DB,$CFG_GLPI, $LANG;
	
	if (!haveRight("entity","r")||!haveRight("user","r"))	return false;

	$entity=new Entity();

	//$canedit=haveRight("entity","w");
	$canedit = $entity->can($ID,"w");
	$canshowuser=haveRight("user","r");
	$nb_per_line=3;
	if ($canedit) $headerspan=$nb_per_line*2;
	else $headerspan=$nb_per_line;
	$rand=mt_rand();
	echo "<form name='entityuser_form$rand' id='entityuser_form$rand' method='post' action=\"$target\">";

	if ($entity->getFromDB($ID)||$ID==0){
		if ($canedit){
	
			echo "<div class='center'>";
			echo "<table  class='tab_cadre_fixe'>";
			echo "<tr class='tab_bg_1'><th colspan='5'>".$LANG['setup'][603]."</tr><tr><td class='tab_bg_2' align='center'>";
			echo "<input type='hidden' name='entities_id' value='$ID'>";
			dropdownAllUsers("users_id",0,1);
			echo "</td><td align='center' class='tab_bg_2'>";
			echo $LANG['profiles'][22].":";
			dropdownUnderProfiles("FK_profiles");
			echo "</td><td align='center' class='tab_bg_2'>";
			echo $LANG['profiles'][28].":";
			dropdownYesNo("recursive",0);
			echo "</td><td align='center' class='tab_bg_2'>";
			echo "<input type='submit' name='adduser' value=\"".$LANG['buttons'][8]."\" class='submit'>";
			echo "</td></tr>";
	
			echo "</table></div><br>";
	
		}
	
	
	
		echo "<div class='center'><table class='tab_cadrehov'><tr><th colspan='$headerspan'>".$LANG['Menu'][14]." (D=".$LANG['profiles'][29].", R=".$LANG['profiles'][28].")</th></tr>";




		$query="SELECT DISTINCT glpi_profiles.ID, glpi_profiles.name 
				FROM glpi_profiles_users 
				LEFT JOIN glpi_profiles ON (glpi_profiles_users.FK_profiles = glpi_profiles.ID)
				LEFT JOIN glpi_users ON (glpi_users.ID = glpi_profiles_users.users_id)
				WHERE glpi_profiles_users.entities_id='$ID' AND glpi_users.deleted=0;";
	
		$result=$DB->query($query);
		if ($DB->numrows($result)>0){
	
			while ($data=$DB->fetch_array($result)){
				echo "<tr><th colspan='$headerspan'>".$data["name"]."</th></tr>";

				$query="SELECT glpi_users.*,glpi_profiles_users.ID as linkID,glpi_profiles_users.recursive,glpi_profiles_users.dynamic
					FROM glpi_profiles_users 
					LEFT JOIN glpi_users ON (glpi_users.ID = glpi_profiles_users.users_id) 
					WHERE glpi_profiles_users.entities_id='$ID' AND glpi_users.deleted=0 AND glpi_profiles_users.FK_profiles='".$data['ID']."'   
					ORDER BY glpi_profiles_users.FK_profiles, glpi_users.name, glpi_users.realname, glpi_users.firstname";
				$result2=$DB->query($query);
				if ($DB->numrows($result2)>0){
					$i=0;
					while ($data2=$DB->fetch_array($result2)){
	
						if ($i%$nb_per_line==0) {
							if ($i!=0) echo "</tr>";
							echo "<tr class='tab_bg_1'>";
						}
						if ($canedit){
							echo "<td width='10'>";
							$sel="";
							if (isset($_GET["select"])&&$_GET["select"]=="all") $sel="checked";
							echo "<input type='checkbox' name='item[".$data2["linkID"]."]' value='1' $sel>";
							echo "</td>";
						}
			
						echo "<td>";
			
						echo formatUserName($data2["ID"],$data2["name"],$data2["realname"],$data2["firstname"],$canshowuser);
						if ($data2["dynamic"]||$data2["recursive"]){
							echo "<strong>&nbsp;(";
							if ($data2["dynamic"]) echo "D";
							if ($data2["dynamic"]&&$data2["recursive"]) echo ", ";
							if ($data2["recursive"]) echo "R";
							echo ")</strong>";
						}
						echo "</td>";
						$i++;
					}
					while ($i%$nb_per_line!=0){
						echo "<td>&nbsp;</td>";
						if ($canedit) echo "<td>&nbsp;</td>";
						$i++;
					}
					echo "</tr>";

				} else {
					echo "<tr colspan='$headerspan'>".$LANG['common'][54]."</tr>";
				}



			}

		}
	
		echo "</table></div>";
	
		if ($canedit){
			echo "<div class='center'>";
			echo "<table width='80%' class='tab_glpi'>";
			echo "<tr><td><img src=\"".$CFG_GLPI["root_doc"]."/pics/arrow-left.png\" alt=''></td><td class='center'><a onclick= \"if ( markCheckboxes('entityuser_form$rand') ) return false;\" href='".$_SERVER['PHP_SELF']."?ID=$ID&amp;select=all'>".$LANG['buttons'][18]."</a></td>";
	
			echo "<td>/</td><td class='center'><a onclick= \"if ( unMarkCheckboxes('entityuser_form$rand') ) return false;\" href='".$_SERVER['PHP_SELF']."?ID=$ID&amp;select=none'>".$LANG['buttons'][19]."</a>";
			echo "</td><td align='left' width='80%'>";
			echo "<input type='submit' name='deleteuser' value=\"".$LANG['buttons'][6]."\" class='submit'>";
			echo "</td>";
			echo "</table>";
	
			echo "</div>";
	
		}
		echo "</form>";
	}
}

/**
 * Add a right to a user 
 *
 * @param $input array : parameters : need entities_id / users_id / FK_profiles optional : recurisve=0 / dynamic=0
 * @return new glpi_profiles_users ID
 */
function addUserProfileEntity($input){
	global $DB;
	if (!isset($input['entities_id'])||$input['entities_id']<0
		||!isset($input['users_id'])||$input['users_id']==0
		||!isset($input['FK_profiles'])||$input['FK_profiles']==0) {
		return false;
	}
	if (!isset($input['recursive'])){
		$input['recursive']=0;
	}
	if (!isset($input['dynamic'])){
		$input['dynamic']=0;
	}

	$query="INSERT INTO `glpi_profiles_users` ( `users_id` , `FK_profiles` , `entities_id` , `recursive` , `dynamic` )
		VALUES ('".$input['users_id']."', '".$input['FK_profiles']."', '".$input['entities_id']."', '".$input['recursive']."', '".$input['dynamic']."');";
	
	return $DB->query($query);
}

/**
 * Delete a right to a user 
 *
 * @param $ID integer : glpi_profiles_users ID
 */
function deleteUserProfileEntity($ID){

	global $DB;

	$query="SELECT users_id FROM glpi_profiles_users WHERE ID = '$ID';";
	$result = $DB->query($query);
	$data=$DB->fetch_assoc($result);
	
	$query="DELETE FROM glpi_profiles_users WHERE ID = '$ID';";
	$result = $DB->query($query);
}

/**
 * Move a right to another entity
 *
 * @param $ID integer : glpi_profiles_users ID
 * @param $entities_id integer : new entity ID
 */
function moveUserProfileEntity($ID,$entities_id){

	global $DB;
	$query="UPDATE glpi_profiles_users SET entities_id='$entities_id' WHERE ID = '$ID';";
	return $DB->query($query);
}



function getEntityIDByField($field,$value)
{
	global $DB;
	$sql = "SELECT entities_id FROM glpi_entitiesdatas WHERE ".$field."='".$value."'";
	
	$result = $DB->query($sql);
	if ($DB->numrows($result)==1)
		return $DB->result($result,0,"entities_id");
	else
		return "";	
}

function getEntityIDByDN($value)
{
	return getEntityIDByField("ldap_dn",$value);
}

function getEntityIDByTag($value)
{
	return getEntityIDByField("tag",$value);
}

?>