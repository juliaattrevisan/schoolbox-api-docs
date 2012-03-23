$j(function() {
    var markSheet =  {

        columnCount : $j('.mark-sheet.assessments table').find('tr:first').children('th').size(),
        assessmentCellWidth : '150',

        init: function() {
            markSheet.columns();
            markSheet.inputs();
            markSheet.cells();
        },


        columns: function() {
            for (i=0; i <= markSheet.columnCount; i++) {
                $j('.mark-sheet.assessments table').find('tr:first').children('th:nth-child('+i+')').css({
                    'width': markSheet.assessmentCellWidth,
                    'padding-bottom': 0
                });
            };

            $j('.mark-sheet.assessments table').css('width', markSheet.assessmentCellWidth * markSheet.columnCount);

            $j('.height-control').ellipsis();


        },

        inputs: function() {

            $j('.inputMark, .comment').blur(function(){
                var $this = $j(this),
                    notification = $j(this).parent().siblings('.save-notification');

                if ($this.val() != '') {
                    $j.post('/submission/returnWorkForm.php', $this.closest('form').serialize(), function(data) {
                        notification.clearQueue();
                        notification.stop().fadeIn(500).delay(1000).fadeOut(500);
                    });
                }
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

                    $j('.mark-sheet.assessments table').css('width', markSheet.assessmentCellWidth * markSheet.columnCount);

                    $j('td:not(".'+assignmentClass+'")').removeClass('active-assessment');

                    $j('.comment').each(function() {
                        $j(this).hide();
                    });

                    $j('.'+assignmentClass).each(function() {
                        $j(this).find('.comment').show();
                        $j(this).addClass('active-assessment');
                    })

                    $j('.mark-sheet.assessments table').css('width',$j('.mark-sheet.assessments table').width() + 350);

                }
            });

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

        }

    }

    markSheet.init();

});