<?php 
/**
* Defines methods for creating a web page on the Intranet.
*
* @package Web
* @author Krista Sheely
*/ 
class Web_Page { 	
	/**
	* The module of the page that's being loaded.
	* @var integer
	*/
	public $page_module = 0;
	
	/**
	* Whether or not to print the sidebar of the page. 
	* @var bool
	*/
	public $print_sidebar;
	
	/**
	* The title of the page. 
	* The default title is "Intercollegiate Athletics Intranet."
	* @var string
	*/
	public $page_title = "Intercollegiate Athletics Intranet";
	
	/**
	* Any stylesheet that should be loaded in the header of the page. 
	* @var string
	*/
	public $stylesheet = "";
	
	/**
	* The database handle.
	* @var PDO
	*/	
	public $db;
	
	/**
	* A string for printing a mailto webmaster link.
	* @var string
	*/	
	public $webmaster = "<a href=\"mailto:icaweb@umn.edu\">ICA Technology Services</a>";
	
	/**
	* The last time this page was modified. UNIX timestamp.
	* @var integer
	*/
	public $modification_date = 0;
	
	/**
	* The x500 of the logged-in user. "guest" if there is no login on the page.
	* @var string
	*/	
	public $user;
	
	/**
	* The path to the site root.
	* @var string
	*/
	public $root_path;
	
	/**
	* The user's class. 0 is Site Administrator, 1 is Administrator, 2 is User.
	* @var integer
	*/
	public $user_class = 4;
	
	/**
	* Whether or not the user is able to edit content.
	* @var integer
	*/
	public $editor = 0;
	
	/**
	* The user's row from the users table. 
	* @var Web_User
	* @see Web_User
	*/
	public $userrow = array();
	
	/**
	* A string representing the user's modules.
	* @var string
	*/
	public $admin_modules = "";
	
	/**
	* The page module's row from the modules table.
	* @var Web_Module
	* @see Web_Module
	*/
	public $pagemodulerow = array();
	
	/**
	* The user's primary module.
	* @var integer
	*/
	public $module = 0;
	
	/**
	* The row from the modules table corresponding to the user's primary module.
	* @var Web_Module
	* @see Web_Module
	*/
	public $modulerow = array();
	
	/**
	* An array of Web_Module objects, corresponding to all of the modules for which the user has an administrative permission.
	* @var array
	* @see Web_Module
	*/
	public $admin_module_array = array();	
	

	public $printTopNav = true;


	/**
	* Constructor for the page. Sets up most of the properties for this object.
	* @param $page_module integer The module (i.e. unit) for this page.
	* @param $print_sidebar bool Whether or not to print the page's sidebar. Default is true.
	*/
	function __construct ($page_module, $print_sidebar = true) { 
		$this->page_module = $page_module;
		$this->print_sidebar = $print_sidebar;
		
			//Connect to database
		$this->db = $this->connect();	
		$pagemodulemapper = new Data_ModuleMapper($this->db);	
		
		//Set up $this->user;
		if (!isset($_SERVER['REMOTE_USER']) || trim($_SERVER['REMOTE_USER']) == "") {
			$this->user = "guest";
		} 		else {
			$this->user = $_SERVER['REMOTE_USER'];	
			//Set up user variables
			$usermapper = new Data_UserMapper($this->db);
			$userobj = $usermapper->find($this->user);
		if (!$userobj) { 
				//User is not registered for the site
				$this->user_class = 4;
				$this->editor = 0;  
			} 	else { 
				$this->user_class = $userobj->{'class'};	
				if ($this->user_class < 2) { 
					$this->editor = 1;
				} else { 
					$this->editor = 0;
				}
			$this->userrow = $userobj;
				$this->admin_modules = $userobj->admin_modules;
				
				//Set up information for user module
				$this->module = $userobj->module;
				$moduleobj = $pagemodulemapper->find($this->module);
				$this->modulerow = $moduleobj;
				
				//Set up admin_module_array
				$admin_modules = split(" ",trim($this->admin_modules));
				while ($admin_module = array_shift($admin_modules)) {
					if ($admin_module == "") { 
						continue;
					}
					$admmodobj = $pagemodulemapper->find($admin_module);			
					$this->admin_module_array[] = $admmodobj;
				}
			}
		}
		
		//Set up page module information
		$pagemoduleobj = $pagemodulemapper->find($this->page_module);
		$this->pagemodulerow = $pagemoduleobj;
	/*	*/
		for ($i = 1; $i <= sizeof(split("/",$_SERVER['PHP_SELF'])) - 2; $i++) {
			$this->root_path .= "../";
		}
	}
	
	/**
	* Set the title of the page.
	* Must be called before printHeader. 
	* @param $page_title string The title of the page.
	*/
	function setTitle ($page_title) { 
		if (trim($page_title) != "") { 
			$this->page_title = $page_title;
		}
	}
	
	/**
	* Set any stylesheets that need to be loaded in the header.
	* Must be called before printHeader.
	* @param $new_stylesheet string The stylesheet to load.
	*/
	function addStylesheet ($new_stylesheet) { 
		if (trim($new_stylesheet) != "") { 
			$this->stylesheet .= $new_stylesheet;
		}
	}
	
	/**
	* Set permissions for the page. Will print Access Denied page and kill the page if needed.
	* @param $restrict_module integer The module to which access should be restricted. 
	* @param $restrict_class integer The user class to which access should be restricted. 
	* @param $restrict_user string Whether or not the page should be restricted to a certain user. 
	* @param $allow_secondary_module boolean Whether the page should allow those with secondary module restrictions for $restrict_module to access the page.
	*/
	function setPermissions ($restrict_module = 0, $restrict_class = 2, $restrict_user = "", $allow_secondary_module = true) { 
		$allowed = true;
		
		if ($this->user_class != 0 && $restrict_module != 0 && $this->module != $restrict_module && strstr($this->admin_modules,"$restrict_module") === false && ($allow_secondary_module === false || ($allow_secondary_module === true && strstr($this->userrow->secondary_modules, "$restrict_module") === false))) { 
			$allowed = false;
		} elseif ($this->user_class > $restrict_class) {
			$allowed = false;
		} elseif ($restrict_user != "" && stristr($restrict_user, $this->user) === false && $this->user_class !=0) { 
			$allowed = false;
		}
		
		if ($allowed === false) {  
			$this->setTitle("Access Denied"); 
			$this->printHeader();
			$this->printSidebar(false);
			echo "<p class=\"subheadline\">You are not authorized to view this page.</p>";
			echo "<p>User: " . $this->user . "<br />Please contact ".$this->webmaster." if you believe you have received this message in error.</p>";		
			$this->endContent();
			$this->printFooter();
			$this->finishPage();
			$this->db = NULL;
			die();
		}
	}
	
	/**
	* Connect to the database. 
	* @see PDO
	*/
	function connect() { 
		//Set up PDO connection
		try { 
			$db = new PDO("mysql:host=localhost;dbname=intranet", "root", "Ica11n3;)pw");
			//define('DB_PATH', );
			//$db = new PDO('sqlite:'. $_SERVER['DOCUMENT_ROOT'] . '/intranet.db');
			$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
			return $db;
		}
		catch(PDOException $e) {
			echo "Error: Unable to load this page. Please contact icaweb@umn.edu for assistance.";
			//echo "<br/>". $_SERVER['DOCUMENT_ROOT']. '/intranet.db';
			echo "<br/>Error: " .$e;
		}




	}
	
	/**
	* Print the header of the page. 
	*/
	function printHeader($printTopNav = true) {
		?>
        <!DOCTYPE html>
		<html lang="en">
		<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
		<title><?php echo $this->page_title; ?></title>

		<link rel="stylesheet" href="/include/HeaderFooter/css/2015-tc.css">

		<!-- Bootstrap -->
		<link href="/include/bootstrap/css/bootstrap.css" rel="stylesheet">

		<!-- Added CSS created to customize Atheletics Intranet Page -->
		<link href="/include/css/addedCss.css" rel="stylesheet">

		<!--	Script below controls dropdown toggle and footer accordion. -->
		<script src="/include/HeaderFooter/js/umnhf-2015.js" type="text/javascript"></script>
		<script src="/include/HeaderFooter/js/html5shiv-printshiv.js" type="text/javascript"></script>

 		<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
	    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	    <!-- Include all compiled plugins (below), or include individual files as needed -->
	    <script src="/include/bootstrap/js/bootstrap.min.js"></script>


		<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
		<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
        
        <?php echo $this->stylesheet; ?>

        
			<script type="text/javascript">
          (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
          (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
          m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
          })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
        
          ga('create', 'UA-50275315-1', 'umn.edu');
          ga('send', 'pageview');
		
		</script>
        
        
        </head>
        
        <body>
  		<!-- --------------------------------------------------------------- -->
 		<!-- BEGIN UNIVERSITY OF MINNESOTA HEADER -->
 		<!-- --------------------------------------------------------------- -->

		<header class="umnhf" id="umnhf-h" role="banner">
			<!-- Skip Links: Give your nav and content elements the appropriate ID attributes -->
			<div id="skipLinks"><a href="#main-nav">Main navigation</a><a href="#main-content">Main content</a></div>
			<div class="printer"><div class="left"></div><div class="right"><strong>University of Minnesota</strong><br />http://twin-cities.umn.edu/<br />612-625-5000</div></div>
			<div class="umnhf" id="umnhf-h-mast">
				<a class="umnhf" id="umnhf-h-logo" href="http://twin-cities.umn.edu/"><span>Go to the U of M home page</span></a>
				<ul class="umnhf" id="umnhf-h-ql">
					<li><a href="http://onestop.umn.edu/">One Stop</a></li>
					<li class="umnhf"><a href="https://www.myu.umn.edu/">MyU <span></span>: For Students, Faculty, and Staff</a></li>
				</ul>
				<!-- Button below is for dropdown toggle, only visible on mobile screens. If using
				a non-dropdown version you can delete this tag -->
				<button class="umnhf" id="umnhf-m-search">Search</button>
			</div>
			<form class="umnhf" id="umnhf-h-search" action="//search.umn.edu/tc/" method="get" title="Search Websites and People" role="search">
				<label class="umnhf" for="umnhf-h-st">Search</label>
				<input class="umnhf" id="umnhf-h-st" type="text" name="q" />
				<label class="umnhf" for="umnhf-h-sb">Submit search query</label>
				<input class="umnhf" id="umnhf-h-sb" type="submit" value="">
			</form>
		</header>
  		<!-- --------------------------------------------------------------- -->		
		<!-- END UNIVERSITY OF MINNESOTA HEADER -->
  		<!-- --------------------------------------------------------------- -->		

 		<?php if($printTopNav == true) { ?>
 
   		<!-- --------------------------------------------------------------- -->		
		<!-- START GOPHER TOP NAV -->
  		<!-- --------------------------------------------------------------- -->			
 		<div class="container" style="padding: 0px">
		



		<nav class="navbar navbar-default">
	 
	    <!-- Brand and toggle get grouped for better mobile display -->
	    <div class="navbar-header">
	      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
	        <span class="sr-only">Toggle navigation</span>
	        <span class="icon-bar"></span>
	        <span class="icon-bar"></span>
	        <span class="icon-bar"></span>
	      </button>
	      <a class="navbar-brand" href="<?php echo $this->root_path; ?>index.php">Intranet Home</a>
	    </div>

	    <!-- Collect the nav links, forms, and other content for toggling -->
	    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
	      <ul class="nav navbar-nav">
	        <li class="dropdown">
	          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Quick Links <span class="caret"></span></a>
	          <ul class="dropdown-menu">
	            <li><a href="<?php echo $this->root_path; ?>athladministration/files/ICA%20Phone%20&%20Email%20Directory.pdf" target="_blank">Athletics Directory</a></li>
	            <li><a href="<?php echo $this->root_path; ?>athladministration/files/Athletic_Org_Chart.pdf"  target="_blank">Athletics Org Chart</a></li>
	            <li><a href="<?php echo $this->root_path; ?>athladministration/files/16-17%20Sport%20Staff%20Assignment.pdf"  target="_blank">Athletics Sports Staff Assignments</a></li>
	            <li <?php if($this->page_module == 136) echo 'class="active"';?>><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/newemployee/private/">New Employee Resources</a></li>
	          </ul>
	        </li>
			
			<li class="dropdown">
	          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Support Departments <span class="caret"></span></a>
	          <ul class="dropdown-menu">
					<li <?php if($this->page_module == 163) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>athladministration/">Administration</a></li>
					<li <?php if($this->page_module == 134) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>training/">Athletic Medicine</a></li>
					<li <?php if($this->page_module == 135) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>finance/">Business Office</a></li>
					<li <?php if($this->page_module == 159) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>communications/">Communications</a></li> 
					<li <?php if($this->page_module == 132) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>compliance/">Compliance</a></li> 
					<li <?php if($this->page_module == 183) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>creativeservices/">Creative Services</a></li>   
					<li <?php if($this->page_module == 161) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>credentials/">Credentials Request</a></li>    
					<li <?php if($this->page_module == 136) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>facilities/">Facilities</a></li>
					<li <?php if($this->page_module == 190) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>hr/">Human Resources</a></li>    

					<li <?php if($this->page_module == 103) echo 'class="active"';?>><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/letterwinner/">Letterwinner</a></li> 

					<li <?php if($this->page_module == 158) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>marketing/">Marketing</a></li>                
					<li <?php if($this->page_module == 100) echo 'class="active"';?>><a href="<?php echo $this->root_path; ?>technology1/">Technology Services</a></li> 
					<li <?php if($this->page_module == 133) echo 'class="active"';?>><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/ticketoffice/">Ticket Office</a></li>     	        	        
	          </ul>
	        </li>

			<li class="dropdown">
	          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Student Athletes <span class="caret"></span></a>
	          <ul class="dropdown-menu">
			        <li <?php if($this->page_module == 186) echo 'class="active"';?>><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/sadevelopment2">Student-Athlete Development</a></li>
			        <li <?php if($this->page_module == 185) echo 'class="active"';?>><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/athletesurvey">Athlete Surveys </a></li>
			        <li <?php if($this->page_module == 188) echo 'class="active"';?>><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/newAthleteQuestionnaire">New Athlete Questionnaire</a></li>        	        
	          </ul>
	        </li>


      
            <?php
      /*      if (!isset($_SERVER['REMOTE_USER']) || trim($_SERVER['REMOTE_USER']) == "") {
                $login_url = Web_Shibboleth::shib_login_and_redirect_url();
                $login_logout = "<a href=\"$login_url\"><strong>Log In</strong></a>";
            } else {
                $logout_url = Web_Shibboleth::shib_logout_url();
                $login_logout = "<a href=\"$logout_url\"><strong>Log Out</strong></a>";
            }
             
            if (isset($_SERVER['REMOTE_USER'])) {  
                if ($_SERVER['REMOTE_USER'] != "" && $_SERVER['REMOTE_USER'] != "guest") { ?>
                    <ul class="main_nav">
                        <li><?php echo $login_logout;  ?></li> 
                    </ul>
                    <?php 
                } 
            } */
                      
                   
            ?>



	      </ul>
	     
	      <ul class="nav navbar-nav navbar-right">
	       <!-- <li><a href="#">My Profile</a></li>-->
	      </ul>
	    </div><!-- /.navbar-collapse -->
	
		</nav>

	  </div><!-- /.container -->
   		<!-- --------------------------------------------------------------- -->		
		<!-- END GOPHER TOP NAV -->
  		<!-- --------------------------------------------------------------- -->	

  		<?php } ?>

   		<!-- --------------------------------------------------------------- -->		
		<!-- START CONTENT -->
  		<!-- --------------------------------------------------------------- -->	  		

	   <div class="container">

        <?php
		
	}
	
/**
	* Print current location in web site.
	* Must be called after printTopbar
	*/	
	function printBreadCrumbs(){
		?>
			<ol class="breadcrumb">
	  			<li><a href="#">Home</a></li>
	  			<li class="active">Technology Services</li>
			</ol>

		<?php

	}
	
	public $currentPage = "";

	function printSideBar($pagename){
		$currentPage = $pagename;

		
		?>

		<div class="row">
    <div class="col-md-2">
	

   <?php  

			if ($this->page_module != 0 && file_exists($this->root_path . $this->pagemodulerow->shortname . "/includes/sidebar.php")) { 	  
				include($this->root_path . $this->pagemodulerow->shortname . "/includes/sidebar.php"); 
				        	 
		} else if($this->page_module != 0) { 
			?>
                
		<div class="sidebar-nav">
	      <div class="navbar navbar-default" role="navigation">
	        <div class="navbar-header">
	          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".sidebar-navbar-collapse">
	            <span class="sr-only">Toggle navigation</span>
	            <span class="icon-bar"></span>
	            <span class="icon-bar"></span>
	            <span class="icon-bar"></span>
	          </button>
	          <span class="visible-xs navbar-brand"></span>
	        </div>
	        <div class="navbar-collapse collapse sidebar-navbar-collapse">
	          <ul class="nav navbar-nav">
	            <li class="active"><a href="<?php echo $this->root_path . $this->pagemodulerow->shortname; ?>/index.php">Home</a></li>
           </ul>


        </div><!--/.nav-collapse -->
        </div>
      </div><!--/.sidebar-nav -->

 <?php 

		} else {
?>

<div class="sidebar-nav">
	      <div class="navbar navbar-default" role="navigation">
	        <div class="navbar-header">
	          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".sidebar-navbar-collapse">
	            <span class="sr-only">Toggle navigation</span>
	            <span class="icon-bar"></span>
	            <span class="icon-bar"></span>
	            <span class="icon-bar"></span>
	          </button>
	          <span class="visible-xs navbar-brand"></span>
	        </div>
	        <div class="navbar-collapse collapse sidebar-navbar-collapse">
	          <ul class="nav navbar-nav">
	            <li class="active"><a href="index.php">Home</a></li>
           </ul>


        </div><!--/.nav-collapse -->
        </div>
      </div><!--/.sidebar-nav -->



<?php 
		}
		?>


    </div> <!-- /.col-md-2 -->


    <div class="col-md-10">

    	<!---  START PAGE CONTENT --------------- -->


    <?php
	}
	
	/**
	* End printing the page content. 
	*/
	function endContent() { 
		?>
         
         	</div><!-- /.col-md-10 -->

			</div> <!-- /.row -->	

		 </div> <!-- /.container -->
        
        <!-- END PAGE CONTENT -->
        <?php 
	}
	
	/**
	* Print the page footer. 
	* Must be called after endContent.
	*/
	function printFooter() { 
		?>
     <!-- --------------------------------------------------------------- -->
		<!-- BEGIN UNIVERSITY OF MINNESOTA FOOTER -->
		<!-- --------------------------------------------------------------- -->
		<section id="umnhf-uf" class="umnhf">
			<div class="umnhf-uf-sub">
				<h2 class="visually-hidden">Contact Information</h2>
				<address id="umnhf-uf-ci" class="umnhf">
					<p class="umnhf-f-title">Intercollegiate Athletics</p>
					<p>516 15th Ave. SE, Suite 250</p>
					<p>Minneapolis, MN 55455</p>
					<p><abbr title="Phone Number">P</abbr>: <a href="tel:0000000000">612-624-4497</a> | <abbr title="Fax Nubmer">F</abbr>: <a href="tel:1111111111">612-626-7859</a></p>					
				</address>
				<!-- OPTIONAL SOCIAL MEDIA LINKS -->
				<!--<section id="umnhf-uf-sm" class="umnhf">
				
					<h2 class="visually-hidden">Connect on Social Media</h2>
					<ul>
						<li class="facebook"><a href="#"><span class="visually-hidden">Facebook</span></a></li><li class="twitter"><a href="#"><span class="visually-hidden">Twitter</span></a></li><li class="google-plus"><a href="#"><span class="visually-hidden">Google Plus</span></a></li><li class="linkedin"><a href="#"><span class="visually-hidden">Linked In</span></a></li><li class="youtube"><a href="#"><span class="visually-hidden">YouTube</span></a></li>
					</ul>
				</section>-->
			</div>
			<div class="umnhf-uf-control">
				<div id="umnhf-uf-ctrl">
					<a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/admin">Control Panel</a>
				</div>
			</div>

			<div class="umnhf-uf-logout">
				<div id="umnhf-uf-lgt">

		<?php
            if (!isset($_SERVER['REMOTE_USER']) || trim($_SERVER['REMOTE_USER']) == "") {
                $login_url = Web_Shibboleth::shib_login_and_redirect_url();
                $login_logout = "<a href=\"$login_url\">Log In</a>";
            } else {
                $logout_url = Web_Shibboleth::shib_logout_url();
                $login_logout = "<a href=\"$logout_url\">Log Out</a>";
            }
             
            if (isset($_SERVER['REMOTE_USER'])) {  
                if ($_SERVER['REMOTE_USER'] != "" && $_SERVER['REMOTE_USER'] != "guest") { 
                    echo $login_logout;  
                } 
            } 


					
					?>
				</div>
			</div>			
			
		</section>

		<footer id="umnhf-f" class="umnhf" role="contentinfo">
			<nav id="umnhf-f-myu">
				<h3 class="umnhf-f-title visually-hidden">For Students, Faculty, and Staff</h3>
				<ul>
					<li><a href="http://onestop.umn.edu/">One Stop</a></li>
					<li><a href="https://www.myu.umn.edu/">My U <span></span></a></li>
				</ul>
			</nav>
			<small>&copy; <span id="cdate">2015</span> Regents of the University of Minnesota. All rights reserved. The University of Minnesota is an equal opportunity educator and employer. <a href="http://privacy.umn.edu">Privacy Statement</a></small>
			<!-- Optional last updated link-->
			<small>Current as of <time datetime="2015-02-20">February 20, 2015</time></small>
		</footer>
  		<!-- --------------------------------------------------------------- -->		
		<!-- END UNIVERSITY OF MINNESOTA FOOTER -->
  		<!-- --------------------------------------------------------------- -->		

        <?php 	
	}
	
	/**
	* Closes up the page. Must be called last.
	*/
	function finishPage() { 
		?>
	    
		  </body>
		</html>
        <?php 
		if (isset($this->connection)) { 
			if ($this->connection) {
				mysql_close($this->connection);
			}
		}
	}
}
?>
