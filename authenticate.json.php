<?php
/**
 * authenticate.json
 * mitcho@mit.edu, August 1, 2011
 * https://github.com/mitcho/authenticate.json
 *
 * A JSON/JSONP service for MIT certificate authentication. Tested on scripts.mit.edu.
 *
 * Brief readme at https://github.com/mitcho/authenticate.json
 */

header('content-type: application/json; charset=utf-8');
	    
// Check certificates:
$authenticated = (bool) $_SERVER['SSL_CLIENT_S_DN_CN'];

if ( $authenticated ) {

	$email = strtolower($_SERVER["SSL_CLIENT_S_DN_Email"]);
	
	//get name from ldap
	list($username, $domain) = explode('@', $email);
	$givenName = getLDAPfield('givenName',$username);
	if (is_null($givenName))
		$givenName = '';
	else
		$givenName = $givenName[0];
	$familyName = getLDAPfield('sn',$username);
	if (is_null($familyName))
		$familyName = '';
	else
		$familyName = $familyName[0];
	
	list($year,$dept,$status) = get_year_dept_status($email,$ln,$fn);

}

$json = json_encode(compact(array('authenticated', 'email', 'username', 'domain', 'firstName', 'lastName', 'year', 'dept', 'status')));

echo isset($_GET['callback']) ? "{$_GET['callback']}($json)" : $json;

/**
 * FUNCTIONS
 */
 
# finger
# Last updated 14Dec2005 - jlev

function finger($user)
#queries MIT finger database for information on user
#returns associative array on success, or false on failure
#access this data as $dict['key']
#where 'key' can be one of the following (case sensitive) values
	#name,email
	#phone,phone2
	#address,city,state
	#office
	#department,school,year
	#url,alias
#note that there will be no data in the url field, due to the explode(':')
{
  $host = 'mit.edu';
  $fp = fsockopen($host, 79, $errno, $errstr) or die("$errno: $errstr");
  fputs($fp, "$user\n");
	$data = '';
  while (!feof($fp))
    $data .= fgets($fp, 128);
  fclose($fp);
  
  $data = explode("\r",$data);
  $data = array_slice($data,10);
#strip intro text
  
  $matches = explode(' ',$data[0]);
  if ($matches == 'No matches to your query.') {
    //        	 $_SESSION['feedback'] .=  ' ERROR - No Matches for User in Finger';
  }
  
  $num_matches = $matches[2];    
  if ($num_matches != '1') {
    //        	 $_SESSION['feedback'] .=  ' ERROR - Ambiguous User Name in Finger';
  }
  else {
#unique user name, get info 
    $info = array_slice($data,2);
    
    $dict = array('');
    foreach($info as $i) {
      $x = explode(":",$i);
			if (isset($x[1]))
				$dict[trim($x[0])] = trim($x[1]);
    }
    return $dict;
  }
  return false;
}

function get_year_dept_status($email,$fn,$ln){
  // year = "MIT position", dept = "MIT department"                                      
  // status = "grad, under 1, under 2, under 3, under 4, staff, faculty, affiliate, postdoc, member" 

  // get users dept and year from directory                                              
  list($username,$domain) = explode("@", $email);
  if ($domain == 'mit.edu'){
    $finger_result = finger($username);
    // if can't find a result, try using first name last name combo                      
    if ($finger_result === false){
      $fn = strtok($fn," ");
      $ln = strtok($ln," ");
      $finger_result = finger($fn.'_'.$ln);
    }
    // if still can't find person                                                        
    if ($finger_result === false){
      //unset($_SESSION['feedback']); // clears errors received from finger function
      $status= 'none';
      $year='none';
    }
    else{
      if (isset($finger_result["year"])){
        $year = $finger_result["year"];
        if ($year == "G")
          $status= 'grad';
        else
          $status= 'undergrad';
      }
      else if (isset($finger_result["title"])){
        $title = $finger_result["title"];
        if (strpos($title,"Professor") !== false){
          $status= 'faculty';
          $year=$title;
        }
        else if(strpos($title,"Postdoctoral") !== false){
          $status= 'postdoc';
          $year=$title;
        }
        else if(strpos($title,"G") !== false){
          $status= 'grad';
          $year = $title;
        }
        else if(strpos($title,"Undergraduate") !== false){
          $status= 'undergrad';
          $year = '0';
        }
        else {
          $status= 'staff';
          $year=$title;
        }
      }
      else{ // no year or title in directory --> MIT affiliate
        $status= 'affiliate';
        $year='none';
      }
      if (isset($finger_result["department"])){
        $dept = $finger_result["department"];
      }
      else{
        $dept = 'none';
      }  
    }
  }
  else{
    if ($domain == "alum.mit.edu"){
      $status= 'alum';
      $year='none';
    }
    else{
      $status= 'none';
      $year='none';
    }
  }      

  return array($year,$dept,$status);
}

/*
 * getLDAPfield written by Dan Cogswell
 * cogswell@mit.edu
 * 1/10/06
 *
 * This function will return an *ARRAY* containing the MIT LDAP search results
 * of the search string $field for athena user $username.  It may be useful
 * to run LDAP_printEntireEntry() to determine an appropriate $field string
 * On error, this function returns a null value.  Errors will most likely occur
 * because either the field does not exist for the user, or the username is not
 * valid.
 *
 * Useful values for the $field variable:
 *  'street' = can be used to determine living group
 *  'ou' = department
 *  'title' = can be used to differentiate students from professors
 */
//-----------------------------------------------------------------------------
function getLDAPfield($field,$username){
  $ds=ldap_connect("ldap.mit.edu");  
  $sr=ldap_search($ds, "dc=mit,dc=edu", "uid=$username");
  $entry=ldap_first_entry($ds,$sr); 

  //Only continue if an entry is returned
  if ($entry){

    //If $field exists as an attribute for the entry, return its value
    $attributes=ldap_get_attributes($ds,$entry);
    if (in_array($field,$attributes))
      $result=ldap_get_values($ds,$entry,$field);
  }
  
  ldap_close($ds);
  return($result);
}
