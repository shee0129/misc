<?php 
/**
* Defines methods for creating a web page on the Intranet.
*
* @package Web
* @author Jon Marthaler
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
		} else {
			$this->user = $_SERVER['REMOTE_USER'];	
			//Set up user variables
			$usermapper = new Data_UserMapper($this->db);
			$userobj = $usermapper->find($this->user);
			if (!$userobj) { 
				//User is not registered for the site
				$this->user_class = 4;
				$this->editor = 0;  
			} else { 
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
			$this->printSidebar();
			$this->startContent();
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
			$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
			return $db;
		}
		catch(PDOException $e) {
			echo "Error: Unable to load this page. Please contact icaweb@umn.edu for assistance.";
		}
	}
	
	/**
	* Print the header of the page. 
	*/
	function printHeader() {
		?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
            "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
        
        <!-- University of Minnesota Web template:  v5.101021 -->
        
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="Description" content="University of Minnesota" />
        <meta name="Keywords" content=" " />
        
        <title><?php echo $this->page_title; ?></title>
        
        <?php //<link rel="shortcut icon" href="http://www1.umn.edu/twincities/favicon.ico" type="image/x-icon" /> ?>
        <link rel="shortcut icon" href="http://www.athletics.umn.edu/faviconICA.ico" type="image/x-icon" />
        
        
        <link href="/include/lib/css/reset.css" rel="stylesheet" type="text/css" media="screen" />
        <link href="/include/lib/css/template.css" rel="stylesheet" type="text/css" media="screen" />
        <link href="/include/lib/css/optional.css" rel="stylesheet" type="text/css" media="screen" />
        <link href="/include/lib/css/athletics.css" rel="stylesheet" type="text/css" media="screen" />
        <link href="/include/lib/css/print.css" rel="stylesheet" type="text/css" media="print" />
        <?php echo $this->stylesheet; ?>
        
        <link href="/include/font-awesome/css/font-awesome.min.css" rel="stylesheet" />
        
        <script type="text/javascript" src="/include/lib/js/searchfield.js"></script>
        


        <!-- STYLE SHEETS TO FIX INTERNET EXPLORER INCONSISTENCIES -->
        
        <!--[if IE 6]>
        <style type="text/css" media="screen">
        @import url("/include/lib/css/IE6.css");
        </style>
        <![endif]-->
        <!--[if IE 7]>
        <style type="text/css" media="screen">
        @import url("/include/lib/css/IE7.css");
        </style>
        <![endif]-->
        
        
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
        <div class="bodycontainer">
        <?php 
		if( $_SERVER['SERVER_NAME'] != "www.athletics.umn.edu" ) { 
			?>
            <h1 style="background-color:#FFCC33; border:1px solid #8C1919;">Testing Server</h1>
            <?php 
		}
		?>
        <!--BEGIN WORDMARK AND UNIT IDENTIFICATION FOR PRINT -->
        <!--Use this code along with the print.css to retain the University's wordmark on your printed Web pages and to identify your unit -->
        <div class="leftprint">
        <img src="/include/images/assets/img/smMwdmk.gif" alt="University of Minnesota" width="216" height="55" hspace="10" align="left" />
        </div>
        <div class="rightprint"><strong>University of Minnesota</strong><br />
        http://www.umn.edu/<br />
        612-625-5000<br />
        </div>
        <!--END WORDMARK AND UNIT IDENTIFICATION FOR PRINT -->
        
        <!--  Skip Links  -->
          <p id="skipLinks">
            <a href="#main_nav">Main navigation</a> | 
            <a href="#maincontent">Main content</a>
          </p>
        
        <div id="header">
        
        <!-- BEGIN CAMPUS LINKS -->
            <div id="campus_links">
                <p>Campuses: </p>
                <ul>
                  <li><a href="http://www.umn.edu">Twin Cities</a></li>
                  <li><a href="http://www.crk.umn.edu">Crookston</a></li>
                  <li><a href="http://www.d.umn.edu">Duluth</a></li>
                  <li><a href="http://www.morris.umn.edu">Morris</a></li>
                  <li><a href="http://www.r.umn.edu">Rochester</a></li>
                  <li><a href="http://www.umn.edu/campuses.php">Other Locations</a></li>
                </ul>
            </div>
            <!-- END CAMPUS LINKS -->
        
            
            <!--  BEGIN TEMPLATE HEADER (MAROON BAR) -->
            <div id="headerUofM">
                
              <div id="logo_uofm"><a href="http://www.umn.edu/">Go to the U of M home page</a></div>
                
        
         <!--BEGIN search div-->
              <div id="search_area">
                 <div id="search_nav"><a href="http://onestop.umn.edu/" id="btn_onestop">OneStop</a> <a href="https://www.myu.umn.edu/" id="btn_myu">myU</a></div>
                 
        <div class="search"> 
        <form action="http://google.umn.edu/search" method="get" name="gsearch" id="gsearch" title="Search U of M Web sites">
        <label for="search_field">Search U of M Web sites</label>
        <input type="text" id="search_field" name="q" value="Search U of M Web sites"  title="Search text"  />   
        <input class="search_btn" type="image" src="/include/images/assets/img/search_button.gif" alt="Submit Search" value="Search" />
        <input name="client" value="searchumn" type="hidden" />
        <input name="proxystylesheet" value="searchumn" type="hidden" />
        <input name="output" value="xml_no_dtd" type="hidden" />
        </form>  
        </div> 
                 
        </div></div>
        <!-- end search area -->
             
        </div>
        <!-- End search div -->
        
        <!--END UofM TEMPLATE HEADER-->
        
        <!-- BEGIN PAGE CONTENT -->
        
        
        <!-- BEGIN THREE COLUMN PAGE CONTENT -->
        <div class="container_12" id="bg354">
        <?php 
	}
	
	/**
	* Print the sidebar of the page. 
	* Must be called after printHeader.
	*/
	function printSidebar($suppress_default = false) { 
		?>
        <!--BEGIN LEFT NAVIGATION -->  
        <div class="grid_3" id="main_nav_3">
        <?php
        if ($this->page_module != 0 && file_exists($this->root_path . $this->pagemodulerow->shortname . "/includes/sidebar.php")) { 	  	
			include($this->root_path . $this->pagemodulerow->shortname . "/includes/sidebar.php"); 
        } 
		
		if ($suppress_default === false) {
			?> 
            <ul class="main_nav">
                <li class="relatedlinks">Main Menu</li> 
                <li><a href="http://<?php echo $_SERVER['HTTP_HOST']; ?>">Home</a></li>
              <!--  <li><a href="http://www.gophersports.com" target="_blank">Gophersports.com</a> <i class="icon-external-link"></i></li>-->
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/athladministration/files/ICA%20Phone%20&%20Email%20Directory.pdf" target="_blank">Athletics Directory</a></li>   
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/athladministration/files/Athletic_Org_Chart.pdf" target="_blank">Athletics Org Chart</a></li>   
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/athladministration/files/16-17%20Sport%20Staff%20Assignment.pdf" target="_blank">Athletics Sports Staff Assignments</a></li>                                            
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/newemployee/private/">New Employee Resources</a> <i class="icon-lock"></i></li>
                <?php //<li><a href="https://<?php echo $_SERVER['HTTP_HOST']; /playerguestlist/">Player Guest List</a> <i class="icon-lock"></i></li> ?>
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/letterwinner/">Letterwinner</a> <i class="icon-lock"></i></li> 
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/admin/">Control Panel</a> <i class="icon-lock"></i></li>
                <li class="relatedlinks">Athletics Units</li>
                <li><a href="<?php echo $this->root_path; ?>athladministration/">Administration</a></li>
                <li><a href="<?php echo $this->root_path; ?>training/">Athletic Medicine</a></li>
                <li><a href="<?php echo $this->root_path; ?>finance/">Business Office</a></li>
                <li><a href="<?php echo $this->root_path; ?>communications/">Communications</a></li> 
                <li><a href="<?php echo $this->root_path; ?>compliance/">Compliance</a></li> 
                <li><a href="<?php echo $this->root_path; ?>creativeservices/">Creative Services</a></li>   
                <li><a href="<?php echo $this->root_path; ?>credentials/">Credentials Request</a></li>    
             <!--   <li><a href="<?php //echo $this->root_path; ?>eventmanagement/">Event Management</a></li>              -->
                <li><a href="<?php echo $this->root_path; ?>facilities/">Facilities</a></li>
                <li><a href="<?php echo $this->root_path; ?>hr/">Human Resources</a></li>                
                <li><a href="<?php echo $this->root_path; ?>marketing/">Marketing</a></li>
                                
                <li><a href="<?php echo $this->root_path; ?>technology1/">Technology Services</a></li> 
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/ticketoffice/">Ticket Office</a></li>  
                <li class="relatedlinks">Student Athletes</li>
				<li><a href="<?php echo $this->root_path; ?>sadevelopment2/">Student-Athlete Development</a></li>                
              <!--  <li><a href="https://<?php //echo $_SERVER['HTTP_HOST']; ?>/mgolf/">Men's Golf</a> <i class="icon-lock"></i></li>
                <li><a href="https://<?php //echo $_SERVER['HTTP_HOST']; ?>/wgolf/">Women's Golf</a> <i class="icon-lock"></i></li>       -->
                <li><a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/athletesurvey/">Athlete Surveys</a> <i class="icon-lock"></i></li>
                <li><a href="<?php echo $this->root_path; ?>newAthleteQuestionnaire/">New Athlete Questionnaire</a></li>                              
            </ul>
            
            <?php
            if (!isset($_SERVER['REMOTE_USER']) || trim($_SERVER['REMOTE_USER']) == "") {
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
            } 
		}
		?>
        </div>
        
        <!-- END LEFT NAVIGATION -->
        <?php 
	}
	
	/**
	* Begin printing the page content.
	* Must be called after printHeader and printSidebar.
	*/
	function startContent() { 
		if ($this->print_sidebar === true) { 
			?>
            <!-- BEGIN Unit Graphic Header for secondary page -->
            <div class="grid_9" id="nospace">
            <p class="nopadding" id="main_head"> <a href="/index.php"><img src="/include/images/icabanner.jpg" alt="Go to unit's home page." width="720" height="48" /></a></p>
            <?php 
		} else { 
			?>
            <!-- BEGIN Unit Graphic Header for secondary page -->
            <div class="grid_12" id="nospace">
            <?php 
		}
	}
	
	/**
	* End printing the page content. 
	* Must be called after startContent.
	*/
	function endContent() { 
		?>
        </div>
        </div>
        <br class="clearabove" />
        
        <!-- END PAGE CONTENT -->
        <?php 
	}
	
	/**
	* Print the page footer. 
	* Must be called after endContent.
	*/
	function printFooter() { 
		?>
        <!-- BEGIN OPTIONAL UNIT FOOTER -->
        <div class="grid_12" id="unit_footer2">
        
            <ul class="unit_footer_links">
            <li>Address: 516 15th <acronym class="acronym_border" title="Avenue Southeast">Ave. SE</acronym>, Suite 250, Minneapolis, MN 55455 Phone: 612-624-4497 Fax: 612-626-7859</li>
            <li><a href="http://www.gophersports.com">GopherSports.com</a></li>
            </ul>
        
        </div>
        <!-- END OPTIONAL UNIT FOOTER -->
        
        <!-- BEGIN UofM FOOTER -->
        <div class="grid_7 alpha" id="footer_inner">
           <ul class="copyright"><li>&copy; 2013 Regents of the University of Minnesota. All rights reserved.</li>
            <li>The University of Minnesota is an equal opportunity educator and employer</li>
            <li><?php if(isset($this->modification_date) && $this->modification_date != 0) echo "Last modified on ".date("F jS, Y",$this->modification_date); ?></li></ul>
        </div>
           <div class="grid_5 omega" id="footer_right">
            <ul class="footer_links">
            <li>Twin Cities Campus: </li>
            <li><a href="http://www1.umn.edu/pts/">Parking &amp; Transportation</a></li>
            <li><a href="http://www.umn.edu/twincities/maps/index.html">Maps &amp; Directions</a></li></ul>
            <br class="clearabove" />
            <ul class="footer_links"><li><a href="http://www.directory.umn.edu/">Directories</a></li>
            <li><a href="http://www.umn.edu/twincities/contact/">Contact U of M</a></li>
            <li><a href="http://www.privacy.umn.edu/">Privacy</a></li>
            </ul>
        
        <br class="clearabove" />
        </div>
        <!-- END UofM FOOTER -->
        <?php 	
	}
	
	/**
	* Closes up the page. Must be called last.
	*/
	function finishPage() { 
		?>
        </div>
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
