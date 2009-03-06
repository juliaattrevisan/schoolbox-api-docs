// UDMv4.5 //
///////////////////////////////////////////////////////////////////
var um={'menuClasses':[],'itemClasses':[],'menuCode':[]};
///////////////////////////////////////////////////////////////////
//                                                               //
//  ULTIMATE DROP DOWN MENU Version 4.5 by Brothercake          //
//  http://www.udm4.com/                                         //
//                                                               //
//  This script may not be used or distributed without license   //
//                                                               //
///////////////////////////////////////////////////////////////////




///////////////////////////////////////////////////////////////////
// CORE CONFIGURATION                                            //
///////////////////////////////////////////////////////////////////


//path to images folder
um.baseSRC = "/javascript/udm-resources/";


//initialization trigger element ["id"];
um.trigger = "";


//navbar orientation
um.orientation = [
	"vertical",	// alignment ["vertical"|"horizontal"|"popup"|"expanding"]
	"left",		// h align ["left"|"right"]
	"top",		// v align ["top"|"bottom"]
	"relative",	// positioning ["relative"|"absolute"|"fixed"|"allfixed"]
	"0",	// x position ["em"|"ex"|"px"|"0"]
	"0",	// y position ["em"|"ex"|"px"|"0"]
	"1000",		// z order ["0" to "10000"] (menu takes 20000 headroom)
	];


//navbar list output
um.list = [
	"flexible",	// horizontal overflow ["rigid"|"flexible"]
	"yes",		// -SPARE-
	"no", 		// -SPARE-
	];


//menu behaviors
um.behaviors = [
	"200",		// open timer ["milliseconds"|"0"]
	"500",		// close timer ["milliseconds"|"never"|"0"]
	"yes",		// reposition menus to stay inside the viewport ["yes"|"no"]
	"default",	// manage windowed controls for win/ie ["default","hide","iframe","none"]
	];


//reset behaviors
um.reset = [
	"yes",		// reset from document mouse click ["yes"|"no"]
	"yes",		// reset from window resize ["yes"|"no"]
	"yes",		// reset from text resize ["yes"|"no"]
	"no",		// reset after following link ["yes"|"no"]
	];


//horizontal continuation strip
um.hstrip = [
	"none",		// background ["color"|"#hex"|"rgb()"|"image.gif"|"none"]
	"yes",		// copy navbar item margin-right to margin-bottom ["yes"|"no"]
	];




///////////////////////////////////////////////////////////////////
// MODULE SETTINGS                                               //
///////////////////////////////////////////////////////////////////


//keyboard navigation [comment out or remove if not using]
um.keys = [
	"38",		// up ["n"] ("38" = up arrow key)
	"39",		// right ["n"] ("39" = right arrow key)
	"40",		// down ["n"] ("40" = down arrow key)
	"37",		// left ["n"] ("37" = left arrow key)
	"123",		// hotkey ["n"] ("38" = F12]
	"none",		// hotkey modifier ["none"|"shiftKey"|"ctrlKey"|"altKey"|"metaKey"]
	"27",		// escape ["n"|"none"] ("27" = escape key)
	"document.getElementsByTagName('a')[0]", // exit focus ["js-expression"]
	];




///////////////////////////////////////////////////////////////////
// NAVBAR DEFAULT STYLES                                         //
///////////////////////////////////////////////////////////////////


//styles which apply to the navbar
um.navbar = [
	"-10",		// nav -> menu x-offset (+-)["n" pixels]
	"-10",		// nav -> menu y-offset (+-)["n" pixels]
	"",	// width ["em"|"ex"|"px"] (vertical navbar only - horizontal navbar items have "auto" width) ("%" doesn't work right)
	];


//styles which apply to each navbar item
um.items = [
	"",		// margin between items ["n" pixels]
	"",		// border size ["n" pixels] (single value only)
	"",	// border collapse ["collapse"|"separate"] (only applies when margin = "0")
	"",// border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"",	// border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"",// hover/focus border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"",	// hover/focus border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"",// visited border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"",// visited border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"",		// left/right padding ["n" pixels] (single value only)
	"",		// top/bottom padding ["n" pixels] (single value only)
	"",		// background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",		// hover/focus background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",		// visited background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",		// font size ["em"|"ex"|"%"|"px"|"pt"|"absolute-size"|"relative-size"]
	"",// font family ["font1,font2,font3"] (always end with a generic family name)
	"",		// font weight ["normal"|"bold"|"bolder"|"lighter|"100" to "900"]
	"",		// text decoration ["none"|"underline"|"overline"|"line-through"]
	"",		// text-align ["left"|"right"|"center"]
	"",	// color ["color"|"#hex"|"rgb()"]
	"",	// hover/focus color ["color"|"#hex"|"rgb()"]
	"",	// visited color ["color"|"#hex"|"rgb()"]
	"",	// font-style ["normal"|"italic"|"oblique"]
	"",	// hover/focus font-style ["normal"|"italic"|"oblique"]
	"",	// visited font-style ["normal"|"italic"|"oblique"]
	"",// additional link CSS (careful!)
	"",		// additional hover/focus CSS (careful!)
	"",		// additional visited CSS (careful!)
	"right-red.gif",// menu indicator character/image ["text"|"image.gif"|"none"]
	"",// menu indicator rollover character/image ["text"|"image.gif"|"none"] (must be same type)
	"",		// clipping width of indicator image ["n" pixels] (only when using image arrows)
	"",		// alt text of indicator image ["text"] (only when using image arrows)
	];




///////////////////////////////////////////////////////////////////
// MENU DEFAULT STYLES                                           //
///////////////////////////////////////////////////////////////////


//styles which apply to each menu
um.menus = [
	"-10",		// menu -> menu x-offset (+-)["n" pixels]
	"-10",		// menu -> menu y-offset (+-)["n" pixels]
	"1",		// border size ["n" pixels] (single value only)
	"#002C59",// border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"solid",	// border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"180px",	// width ["em"|"ex"|"px"]
	"",		// padding ["n" pixels] (single value only)
	"",	// background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",		// additional menu CSS (careful!) (you can use a transition here but *not* a static filter)
	"",// shadow background ["color"|"#hex"|"rgb()"|"image.gif"|"none"]
	"",		// shadow offset (+-) ["em"|"px"|"pt"|"%"|"0"]
	"",// additional shadow layer CSS (if you use a Microsoft.Shadow filter here then Win/IE5.5+ will do that *instead* of default shadow)
	];


//styles which apply to each menu item
um.menuItems = [
	"",		// margin around items ["n" pixels] (single value only; margins are like table cellspacing)
	"",		// border size ["n" pixels] (single value only)
	"",	// border collapse ["collapse"|"separate"] (only applies when margin = "0")
	"",	// border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"",	// border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"",		// hover/focus border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"",	// hover/focus border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"",	// visited border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"",	// visited border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"",		// left/right padding ["n" pixels] (single value only)
	"",		// top/bottom padding ["n" pixels] (single value only)
	"",	// background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",	// hover/focus background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",	// visited background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",		// font size ["em"|"ex"|"%"|"px"|"pt"|"absolute-size"|"relative-size"]
	"",// font family ["font1,font2,font3"] (always end with a generic family name)
	"",	// font weight ["normal"|"bold"|"bolder"|"lighter|"100" to "900"]
	"",		// text decoration ["none"|"underline"|"overline"|"line-through"]
	"",		// text-align ["left"|"right"|"center"]
	"",		// color ["color"|"#hex"|"rgb()"]
	"",		// hover/focus color ["color"|"#hex"|"rgb()"]
	"",		// visited color ["color"|"#hex"|"rgb()"]
	"",	// font-style ["normal"|"italic"|"oblique"]
	"",	// hover/focus font-style ["normal"|"italic"|"oblique"]
	"",	// visited font-style ["normal"|"italic"|"oblique"]
	"",		// additional link CSS (careful!)
	"",		// additional hover/focus CSS (careful!)
	"",		// additional visited CSS (careful!)
	"",// submenu indicator character/image ["text"|"image.gif"|"none"]
	"",// submenu indicator rollover character/image ["text"|"image.gif"|"none"] (must be the same type)
	"",		// clipping width of indicator image ["n" pixels] (only when using image arrows)
	"",		// alt text of indicator image ["text"] (only when using image arrows)
	];




///////////////////////////////////////////////////////////////////
// MENU CLASSES [comment out or remove if not using]             //
///////////////////////////////////////////////////////////////////


//classes which apply to menus [optional]
um.menuClasses["orangeMenu"] = [
	"#ddbdc5 #cf0034 #cf0034 #ddbdc5",// border colors ["color"|"#hex"|"rgb()"]
	"solid",	// border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"150px",		// width ["em"|"ex"|"px"]
	"#EEEEE7",		// background ["color"|"#hex"|"rgb()"|"image.gif"]
	"",		// additional menu CSS (careful!) (you can use a transition here but *not* a static filter)
	"redshadow.png",// shadow background ["color"|"#hex"|"rgb()"|"image.gif"|"none"]
	"2px",		// shadow offset (+-) ["em"|"px"|"pt"|"%"|"0"]
	"filter:alpha(opacity=50);", // additional shadow layer CSS (if you use a Microsoft.Shadow filter here then Win/IE5.5+ will do that *instead* of default shadow)
	];


//classes which apply to menu items [optional]
um.itemClasses["orangeMenuItem"] = [
	"#f0f0f0",		// border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"solid",	// border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"#f0f0f0",	// hover/focus border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"solid",	// hover/focus border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"#f0f0f0",		// visited border colors ["color"|"#hex"|"rgb()"] (single, double or four values)
	"solid",	// visited border styles ["solid"|"double"|"dotted"|"dashed"|"groove"|"ridge"|"inset"|"outset"] (single, double or four values; be careful with using "none")
	"transparent",		// background ["color"|"#hex"|"rgb()"|"image.gif"]
	"#DEDEDB",	// hover/focus background ["color"|"#hex"|"rgb()"|"image.gif"]
	"transparent",		// visited background ["color"|"#hex"|"rgb()"|"image.gif"]
	"70%",		// font size ["em"|"ex"|"%"|"px"|"pt"|"absolute-size"|"relative-size"]
	"tahoma,sans-serif",// font family ["font1,font2,font3"] (always end with a generic family name)
	"normal",	// font weight ["normal"|"bold"|"bolder"|"lighter|"100" to "900"]
	"none",		// text decoration ["none"|"underline"|"overline"|"line-through"]
	"left",		// text-align ["left"|"right"|"center"]
	"#222223",	// color ["color"|"#hex"|"rgb()"]
	"#Cf0034",	// hover/focus color ["color"|"#hex"|"rgb()"]
	"#222223",	// visited color ["color"|"#hex"|"rgb()"]
	"normal",	// font-style ["normal"|"italic"|"oblique"]
	"normal",	// hover/focus font-style ["normal"|"italic"|"oblique"]
	"normal",	// visited font-style ["normal"|"italic"|"oblique"]
	"",		// additional link CSS (careful!)
	"",		// additional hover/focus CSS (careful!)
	"",		// additional visited CSS (careful!)
	"right-purple.gif",// submenu indicator character/image ["text"|"image.gif"|"none"] (must be the same type as default submenu indicator)
	"right-blue.gif",// submenu indicator rollover character/image ["text"|"image.gif"|"none"] (must be the same type)
	"..",		// alt text of indicator image  ["text"] (only when using image arrow)
	];




///////////////////////////////////////////////////////////////////
// DYNAMIC MENUS                                                 //
///////////////////////////////////////////////////////////////////




