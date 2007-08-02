<?

require_once( 'include/helpDeskPage.php');
require_once("include/dbManager-mysql.php");

class reportModelStat
{
	var $db;
	var $lu;
	var $tpl;
	
	function reportModelStat()
	{
		$this->tpl = new Module(TPL_DIR);
		$this->tpl->set_file('tplContent', 'reportModelTasks.html');
		$this->tpl->set_block('tplContent','brand', 'brandList');
		$this->tpl->set_block('brand','model', 'modelList');
		$this->db = new dbManager(DB_NAME, 1); 
		$this->db->dbConnect();
		$this->page = new HelpDeskPage;
		$this->page->authCheck(3);
	}
	
	function displayPage($begin, $end)
	{
		if($begin && $end)
		{
			$this->begin = $this->convertDateToMysql($begin);
			$this->end = $this->convertDateToMysql($end);
		}
		else
		{
			$this->begin = date('Y-m-d', mktime(0, 0, 0, date("m")  , date("d"), date("Y")-1));
			$this->end = date('Y-m-d');
		}
		
		$this->tpl->set_var("BEGIN_DATE", $this->convertDateFromMysql($this->begin)); 
		$this->tpl->set_var("END_DATE", $this->convertDateFromMysql($this->end)); 

		//get total amount of equipment
		$query = "SELECT COUNT(*) as total FROM tblEquipment WHERE active = 1";
		$this->db->dbDoQuery($query);
		$count = $this->db->dbGetNextArray();
		$this->totalEquip = $count['total'];
		
		//get total amount of tasks for period
		$query = "SELECT COUNT(*) as total FROM tblJob 
		WHERE equipmentID IS NOT NULL AND (createdDate >= '{$this->begin}' AND createdDate <= '{$this->end}')";
		$this->db->dbDoQuery($query);
		$count = $this->db->dbGetNextArray();
		$this->totalTasks = $count['total'];
		
		$this->outputBrands();
		
		$content = $this->tpl->parse("finaloutput", "tplContent");
		$this->page->setContent($content);
		$this->page->setTitle("Model Statistics Report");
		$this->page->printPage();
	}

	function outputBrands()
	{
		$query = "SELECT b.description as brand, b.brandID, COUNT(*) as tasks 
				FROM tblJob j 
				RIGHT JOIN tblEquipment e ON e.equipmentID = j.equipmentID 
				RIGHT JOIN tblModel m ON e.modelID = m.modelID
				RIGHT JOIN tblBrand b ON m.brandID = b.brandID
				WHERE (j.createdDate >= '{$this->begin}' AND j.createdDate <= '{$this->end}')
				GROUP BY m.brandID
				ORDER BY tasks desc
				LIMIT 0,10
				";
		$result = $this->db->dbDoQuery($query);
	
		while($brand = $this->db->dbGetNextArray($result))
		{
			$this->tpl->set_var("BRAND_NAME", $brand['brand']);
			$this->tpl->set_var("BRAND_TASKS", $brand['tasks']);
			
			$this->outputModels($brand['brandID']);
			
			$this->tpl->parse('brandList', 'brand', true);
			$this->tpl->set_var('modelList', '');
		}
	
	}

	function outputModels($id)
	{
		$query = "SELECT m.description as model, e.modelID, COUNT(*) as tasks 
				FROM tblJob j 
				RIGHT JOIN tblEquipment e ON e.equipmentID = j.equipmentID 
				RIGHT JOIN tblModel m ON e.modelID = m.modelID
				WHERE m.brandID = $id AND (j.createdDate >= '{$this->begin}' AND j.createdDate <= '{$this->end}')
				GROUP BY e.modelID
				ORDER BY tasks desc";
				
		
		$result = $this->db->dbDoQuery($query);
	
		while($model = $this->db->dbGetNextArray($result))
		{
			//set totals for each piece of equipment
			$query = "SELECT COUNT(*) as total FROM tblEquipment WHERE modelID = {$model['modelID']} AND active = '1'";
			$this->db->dbDoQuery($query);
			$count = $this->db->dbGetNextArray();
			
		
			$this->tpl->set_var("MODEL_NAME", $model['model']);
			$this->tpl->set_var("MODEL_TASKS", $model['tasks']);
			
			$taskpc = round((($model['tasks'] / $this->totalTasks) * 100), 1);
			$this->tpl->set_var("MODEL_TASK_PERCENTAGE", $taskpc);
			
			$this->tpl->set_var("MODEL_TOTAL", $count['total']);
			
			$populationpc = round((($count['total'] / $this->totalEquip) * 100), 1);
			$this->tpl->set_var("MODEL_POP_PERCENTAGE", $populationpc);
			
			if($count['total'])
				$failurepc = round((($model['tasks'] / $count['total']) * 100), 0);
			else
				$failurepc = '';
			
			$this->tpl->set_var("MODEL_FAILURE", $failurepc);
			
			if($populationpc >= 2)
			{
				if($failurepc > 200)
					$this->tpl->set_var("MODEL_COLOUR", '#ff6666');
				else if($failurepc > 150)
					$this->tpl->set_var("MODEL_COLOUR", '#fc6');
				else
					$this->tpl->set_var("MODEL_COLOUR", 'inherit');
			}
			else
				$this->tpl->set_var("MODEL_COLOUR", 'inherit');
				
			$this->tpl->parse('modelList', 'model', true);
		}
	}
			

	function convertDateToMysql($date)
	{
		list($date, $month, $year) = explode('/', $date);
		return "$year-$month-$date";
	}

	function convertDateFromMysql($date)
	{
		list($year, $month, $date) = explode('-', $date);
		return "$date/$month/$year"; 
	}


	
}//end class

$page = new reportModelStat();
$page->displayPage($_GET['beginDate'], $_GET['endDate']);


?>
