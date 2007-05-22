<?php

class modPage
{
	function modPage($cid=0)
	{
		$this->db = new dbManager;
		$this->db->dbConnect();

		$this->cid = $cid;
	}

	function setCID($cid)
	{
		$this->cid = $cid;
	}

        function savePageTemplate($template)
        {
                $query = "SELECT cid FROM homepage_config WHERE cid = '".$this->cid."'";
                $this->db->dbDoQuery($query);
                if($row = $this->db->dbGetNextArray())
                {
                        $query = "UPDATE homepage_config SET template = '$template' WHERE cid = '".$this->cid."'";
                }
                else
                {
                        $query = "INSERT INTO homepage_config (cid, template) VALUES ('$this->cid', '$template')";
                }
                $this->db->dbDoQuery($query);

        }

        function deletePageTemplate()
        {
                $query = "DELETE FROM homepage_config WHERE cid = '".$this->cid."'";
                $this->db->dbDoQuery($query);
        }

        function deleteComponentPositions()
        {
                $query = "DELETE FROM homepage_comp_location WHERE cid = '".$this->cid."'";
                $this->db->dbDoQuery($query);
        }

        function saveComponentPosition($component, $position, $size)
        {
                $query = "INSERT INTO homepage_comp_location (position, component, size, cid) VALUES ( '$position', '$component', '$size', '".$this->cid."')";
                $this->db->dbDoQuery($query);

        }

        function saveDisplayProperties($large_head_font, $large_head_bg, $large_text_font, $large_text_bg, 
                                        $small_head_font, $small_head_bg, $small_text_font, $small_text_bg)
        {

                $query = "UPDATE homepage_config SET
                        large_head_fontcolour = '$large_head_font' , 
                        large_head_bgcolour = '$large_head_bg', 
                        large_text_fontcolour = '$large_text_font', 
                        large_text_bgcolour = '$large_text_bg',
                        small_head_fontcolour = '$small_head_font' , 
                        small_head_bgcolour = '$small_head_bg', 
                        small_text_fontcolour = '$small_text_font', 
                        small_text_bgcolour = '$small_text_bg' 
			WHERE cid = '".$this->cid."'";

                $this->db->dbDoQuery($query);

        }


        function createSubjectPage()
        {
                //automatically create default subject page

                //clear template and re add 70/30
                $this->savePageTemplate(1);

                //clear components
                $this->deleteComponentPositions();

                //insert required components
                $this->saveComponentPosition(8, 1, 0);
                $this->saveComponentPosition(19, 2, 5);
                $this->saveComponentPosition(2, 3, 5);
                $this->saveComponentPosition(1, 4, 0);
                $this->saveComponentPosition(1, 5, 0);
                $this->saveComponentPosition(12, 6, 7);
                $this->saveComponentPosition(11, 7, 7);
                $this->saveComponentPosition(9, 8, 0);
                $this->saveComponentPosition(10, 9, 0);
                $this->saveComponentPosition(1, 10, 0);

                //setup css attributes
                $this->saveDisplayProperties(
				HOMEPAGE_large_head_font,
				HOMEPAGE_large_head_bg,
				HOMEPAGE_large_text_font,
				HOMEPAGE_large_text_bg,
				HOMEPAGE_small_head_font,
				HOMEPAGE_small_head_bg,
				HOMEPAGE_small_text_font,
				HOMEPAGE_small_text_bg);

        }

        function createSubjectForumPage()
        {
                //automatically create default subject page

                //clear template and re add 70/30
                $this->savePageTemplate(1);

                //clear components
                $this->deleteComponentPositions();

                //insert required components
                $this->saveComponentPosition(8, 1, 0);
                $this->saveComponentPosition(19, 2, 5);
                $this->saveComponentPosition(2, 3, 5);
                $this->saveComponentPosition(1, 4, 0);
                $this->saveComponentPosition(1, 5, 0);
                $this->saveComponentPosition(12, 6, 7);
                $this->saveComponentPosition(11, 7, 7);
                $this->saveComponentPosition(5, 8, 5);
                $this->saveComponentPosition(9, 9, 0);
                $this->saveComponentPosition(10, 10, 0);

                //setup css attributes
                $this->saveDisplayProperties(
				HOMEPAGE_large_head_font,
				HOMEPAGE_large_head_bg,
				HOMEPAGE_large_text_font,
				HOMEPAGE_large_text_bg,
				HOMEPAGE_small_head_font,
				HOMEPAGE_small_head_bg,
				HOMEPAGE_small_text_font,
				HOMEPAGE_small_text_bg);

        }

        function createGroupPage()
        {
                //automatically create default group page

                //clear template and re add 70/30
                $this->savePageTemplate(1);

                //clear components
                $this->deleteComponentPositions();

                //insert required components
                $this->saveComponentPosition(8, 1, 0);
                $this->saveComponentPosition(3, 2, 5);
                $this->saveComponentPosition(2, 3, 5);
				$this->saveComponentPosition(1, 4, 0);
                $this->saveComponentPosition(1, 5, 0);
				$this->saveComponentPosition(4, 6, 7);
                $this->saveComponentPosition(10, 7, 7);
                $this->saveComponentPosition(1, 8, 0);
                $this->saveComponentPosition(1, 9, 0);
                $this->saveComponentPosition(1, 10, 0);

                //setup css attributes
                $this->saveDisplayProperties(
				HOMEPAGE_large_head_font,
				HOMEPAGE_large_head_bg,
				HOMEPAGE_large_text_font,
				HOMEPAGE_large_text_bg,
				HOMEPAGE_small_head_font,
				HOMEPAGE_small_head_bg,
				HOMEPAGE_small_text_font,
				HOMEPAGE_small_text_bg);
        }

        function createGroupForumPage()
        {
                //automatically create default group page

                //clear template and re add 70/30
                $this->savePageTemplate(1);

                //clear components
                $this->deleteComponentPositions();

                //insert required components
                $this->saveComponentPosition(8, 1, 0);
                $this->saveComponentPosition(3, 2, 5);
                $this->saveComponentPosition(2, 3, 5);
                $this->saveComponentPosition(1, 4, 0);
                $this->saveComponentPosition(1, 5, 0);
                $this->saveComponentPosition(5, 6, 7);
                $this->saveComponentPosition(4, 7, 7);
                $this->saveComponentPosition(10, 8, 7);
                $this->saveComponentPosition(1, 9, 0);
                $this->saveComponentPosition(1, 10, 0);

                //setup css attributes
                $this->saveDisplayProperties(
				HOMEPAGE_large_head_font,
				HOMEPAGE_large_head_bg,
				HOMEPAGE_large_text_font,
				HOMEPAGE_large_text_bg,
				HOMEPAGE_small_head_font,
				HOMEPAGE_small_head_bg,
				HOMEPAGE_small_text_font,
				HOMEPAGE_small_text_bg);
        }

        function createFacultyPage()
        {
                //automatically create default faculty page

                //clear template and re add 70/30
                $this->savePageTemplate(1);

                //clear components
                $this->deleteComponentPositions();

                //insert required components
                $this->saveComponentPosition(15, 1, 0);
                $this->saveComponentPosition(8, 2, 10);
                $this->saveComponentPosition(1, 3, 0);
                $this->saveComponentPosition(1, 4, 0);
                $this->saveComponentPosition(1, 5, 0);
                $this->saveComponentPosition(4, 6, 7);
                $this->saveComponentPosition(7, 7, 7);
                $this->saveComponentPosition(6, 8, 7);
                $this->saveComponentPosition(10, 9, 7);
                $this->saveComponentPosition(1, 10, 0);

                //setup css attributes
                $this->saveDisplayProperties(
				HOMEPAGE_large_head_font,
				HOMEPAGE_large_head_bg,
				HOMEPAGE_large_text_font,
				HOMEPAGE_large_text_bg,
				HOMEPAGE_small_head_font,
				HOMEPAGE_small_head_bg,
				HOMEPAGE_small_text_font,
				HOMEPAGE_small_text_bg);
        }


}

?>
