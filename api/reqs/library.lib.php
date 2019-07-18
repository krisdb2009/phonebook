<?php
/*
Phone Book
----------
API Library
----------
Dylan Bickerstaff
----------
Functions used in other API implementations.
*/
if(!isset($singlePointEntry)){http_response_code(403);exit;}
function createModifyOrFindObject($row = array()) {
    global $db;
    $unique = array();
    $rowWithoutUnique = $row;
    if(isset($row['objectid']) && !empty($row['objectid'])) {
        $unique['objectid'] = $row['objectid'];
    }
    foreach($row as $attribute => $value) { //For each attribute in a row
        if(isset(SCHEMA[$attribute]['unique']) && SCHEMA[$attribute]['unique']) { //If attribute is unique according to the schema
            $unique[$attribute] = $value;
        }
    }
    foreach($unique as $uk => $uv) { //Remove unique stuff
        unset($rowWithoutUnique[$uk]);
    }
    unset($rowWithoutUnique['objectid']);
    $object = $db->get('objects', array('objectid'), array('OR' => $unique));
    if(isset($object['objectid'])) { //Already exists, so just modify the data instead of inserting
        $objectID = $object['objectid'];
        if(empty($rowWithoutUnique)) {
            removeObject($objectID);
        } else {
            $db->update('objects', $row, array('objectid' => $objectID));
        }
    } else { //Insert the data
        if(!empty($rowWithoutUnique)) {
            $row['objectid'] = $objectID = bin2hex(random_bytes(5));
            $db->insert('objects', $row);
        } else {
            $objectID = false;
        }
    }
    return $objectID;
}
function createOrFindTag($tag) {
    global $db;
    $tagID = $db->get('tags', array('tagid'), array('text' => $tag));
    if(empty($tagID)) {
        $tagID = bin2hex(random_bytes(5));
        $db->insert('tags', array(
            'tagid' => $tagID,
            'text' => $tag
        ));
    } else {
        $tagID = $tagID['tagid'];
    }
    return $tagID;
}
function createTagLinkForObject($tagID, $objectID) {
    global $db;
    $row = array(
      'tagid' => $tagID,
      'objectid' => $objectID
    );
    if(isset($tagID) && !empty($tagID) && isset($objectID) && !empty($objectID) && !$db->has('tags_objects', $row)) {
        $db->insert('tags_objects', $row);
    }
}
function clearTagsFromObject($objectID) {
    global $db;
    $tags_objects = $db->select('tags_objects', 'tagid', array('objectid' => $objectID));
    foreach($tags_objects as $tagID) {
        $db->delete('tags_objects', array('objectid' => $objectID, 'tagid' => $tagID));
        if($db->count('tags_objects', array('tagid' => $tagID)) == 0) {
            $db->delete('tags', array('tagid' => $tagID));
        }
    }
}
function getTagsFromObject($objectID, $resolveTagText = false) {
    global $db;
    if($resolveTagText) {
        return $db->select('tags_objects', array(
            '[>]tags' => 'tagid'
        ), 'text', array(
            'tags_objects.objectid' => $objectID
        ));
    } else {
        return $db->select('tags_objects', 'tagid', array(
            'objectid' => $objectID
        ));
    }
}
function removeObject($objectID) {
    global $db;
    if(isset($objectID) && !empty($objectID) && $db->has('objects', array('objectid' => $objectID))) {
        clearTagsFromObject($objectID);
        $db->delete('objects', array('objectid' => $objectID));
    }
}
function tagTranslate($tag) {
    global $db;
    $return = array();
    $result = $db->get('translations', array('to'), array(
        'from' => $tag
    ));
    if(empty($result)) {
        $return[0] = $tag;
    } elseif(!empty($result['to'])) {
        $return = explode(' ', $result['to']);
    }
    return $return;
}
function tagFilter($string) {
    $return = array();
    $string = strtolower($string);
    $string = preg_replace('/[^a-z?![:space:]]/', ' ', $string);
    $string = trim($string);
    foreach(explode(' ', $string) as $rawTag) {
        $return = array_merge($return, tagTranslate($rawTag));
    }
    foreach($return as $k => $tag) {
        if(
            strlen($tag) < 2 ||
            empty($tag)
        ) {
            unset($return[$k]);
        }
    }
    return $return;
}
function organizeDatabaseObjects($objects, $includeTags = false) {
    if(is_array($objects)) {
        foreach($objects as $k => $object) {
            if($includeTags) {
                $tags = getTagsFromObject($object['objectid'], true);
                if($tags) {
                    $object['tags'] = $tags;
                }
            }
            $objects[$object['objectid']] = $object;
            unset($objects[$k]);
            unset($objects[$object['objectid']]['objectid']);
            unset($objects[$object['objectid']]['tagid']);
            unset($objects[$object['objectid']]['text']);
            foreach($object as $attributeName => $attributeValue) {
                if(empty($attributeValue)) {
                    unset($objects[$object['objectid']][$attributeName]);
                }
            }
        }
    } else {
        return array();
    }
    return $objects;
}
function importDatabaseObjects($objects) {
    global $db;
    $db->pdo->beginTransaction();
    foreach($objects as $key => $object) { //For every number being imported...
        $tags = array();
        $row = array("objectid" => $key);
        foreach($object as $attribute => $value) { //For every attribute in an import object, check that the attribute exists, otherwise do not add it to the row.
            if(isset(SCHEMA[$attribute]) && $attribute !== 'tags' && !empty($value)) { //If an attribute in the schema and not a tag list or empty.
                if(isset(SCHEMA[$attribute]['type'])) { //Check type constraints
                    if(SCHEMA[$attribute]['type'] == 'number') { //Check number constraint
                        if(ctype_digit($value)) {
                            $value = intval($value); //Convert string to number if it is a string.
                        } else {
                            break;
                        }
                    }
                    if(SCHEMA[$attribute]['type'] == 'choice' && isset(SCHEMA[$attribute]['choices']) && !in_array($value, SCHEMA[$attribute]['choices'])) { //Check choice constraint
                        break;
                    }
                }
                if(isset(SCHEMA[$attribute]['length']) && strlen($value) > SCHEMA[$attribute]['length']) { //Check string length constraint
                    break;
                }
                if(isset(SCHEMA[$attribute]['tagged']) && SCHEMA[$attribute]['tagged']) { //Apply tagged constraint.
                    $tags = array_merge($tags, tagFilter($value));
                }
                $row[$attribute] = $value; //Add the attribute to the row
            } elseif($attribute == 'tags') { //If a tag list
                foreach($value as $rawTag) { //Foreach tag
                    $tags = array_merge($tags, tagFilter($rawTag)); //Filter the input tag and append the output.
                }
            }
        }
        $objectID = createModifyOrFindObject($row);
        if($objectID !== false) {
            clearTagsFromObject($objectID);
            foreach($tags as $tag) {
                $tagID = createOrFindTag($tag);
                createTagLinkForObject($tagID, $objectID);
            }
        }
    }
    $db->pdo->commit();
}
function exportDatabaseObjects($includeTags = false) {
    global $db;
    $objects = $db->select('objects', '*');
    $objects = organizeDatabaseObjects($objects, $includeTags);
    return $objects;
}
?>