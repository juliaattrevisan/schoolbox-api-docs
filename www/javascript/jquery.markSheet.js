$j(function() {
    var markSheet =  {

        columnCount : $j('.mark-sheet.assessments table').find('tr:first').children('th').size(),

        init: function() {
            markSheet.columns();
            markSheet.inputs();
            markSheet.cells();
        },

        columns: function() {
            for (i=0; i <= markSheet.columnCount; i++) {
                $j('.mark-sheet.assessments table').find('tr:first').children('th:nth-child('+i+')').css({
                    'width': '150px',
                    'padding-bottom': 0
                });
            };

            $j('.mark-sheet.assessments table').css('width', 150 * markSheet.columnCount);

            $j('.height-control').ellipsis();


        },

        inputs: function() {
            $j('.inputMark, .comment').blur(function(){
                var $this = $j(this);
                if($this.val() != '')
                    $j.post('/submission/returnWorkForm.php', $this.closest('form').serialize(), function() {
                        console.log($this);
                        $this.closest('.save-notification').show();
                        //$j('.save-notification').delay(1000).fadeOut(500);
                    });
            });

            $j(".inputMark").focusin(function(evt) {
                $this = $j(this);

                /*
                * Row highlighting
                *
                * */
                var rowNumber = $this.closest("tr").prevAll("tr").length + 1;
                $j('table').find('tr').removeClass('highlight-student');

                if (!$j('table').find('tr:nth-child('+rowNumber+')').hasClass('highlight-student')) {
                    $j('table').find('tr:nth-child('+rowNumber+')').addClass('highlight-student');
                }

                /*
                * Comment input showing
                *
                * */

                if (!$this.closest('td').hasClass('active-assessment')) {
                    var assignmentClass = $this.closest('td').attr('class');

                    $j('.mark-sheet.assessments table').css('width', 150 * markSheet.columnCount);

                    $j('td:not(".'+assignmentClass+'")').removeClass('active-assessment');

                    $j('.comment').each(function() {
                        $j(this).hide();
                    });


                    $j('.'+assignmentClass).each(function() {
                        $j(this).find('.comment').show();
                        $j(this).addClass('active-assessment');
                    })

                    $j('.mark-sheet.assessments table').css('width',$j('.mark-sheet.assessments table').width() +350);

                } else {


                }



            });


            $j(".comment").focusin(function(evt) {


            });

            /*$j('td').focusout(function() {
                $j('.mark-sheet.assessments table').css('width', 150 * markSheet.columnCount);
                $j(this).removeAttr('style');
                $j('.comment').hide();
            });
*/


        },

        cells: function() {
            /*
             * Hovering a td should make the entire row change colour
             *
             * */
            $j('td').hover(function(evt) {
                var rowNumber = $j(evt.target).closest("tr").prevAll("tr").length + 1;

                if (!$j('.inputMark').is(':focus')) {
                    $j('table').find('tr').removeClass('highlight-student');

                    if (!$j('table').find('tr:nth-child('+rowNumber+')').hasClass('highlight-student')) {
                        $j('table').find('tr:nth-child('+rowNumber+')').addClass('highlight-student');
                    }
                }
            }, function() {
                if (!$j('.inputMark').is(':focus')) {
                    $j('table').find('tr').removeClass('highlight-student');
                }
            })

        },


        isInColumn: function(evt) {
           var columnClass = $j(evt.target).closest('td').attr('class');
           /*if () {

           }*/


        }

    }

    markSheet.init();

});