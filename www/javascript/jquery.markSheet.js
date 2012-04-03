$j(function() {
    var markSheet =  {

        columnCount : $j('.mark-sheet.assessments table').find('tr:first').children('th').size(),
        assessmentCellWidth : '180',

        init: function() {
            markSheet.columns();
            markSheet.inputs();
            markSheet.cells();
            markSheet.cellValidation();
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

            $j(".mark-input").focusin(function(evt) {
                $this = $j(this);
                //markSheet.columns();
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

                    var assignmentClass = $this.closest('td');

                    $j('.active-assessment').find('.comment').animate({width:0}, function() {

                        $j('td').not(assignmentClass).removeClass('active-assessment');

                        $j(this).hide();



                     });

                    $j('.mark-sheet.assessments table').css('width', markSheet.assessmentCellWidth * markSheet.columnCount);

                    $j(assignmentClass).each(function() {
                        $j(this).find('.comment').show().animate({width:200});
                        $j(this).addClass('active-assessment');
                    });

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

                if (!$j('.mark-input').is(':focus')) {
                    $j('table').find('tr').removeClass('highlight-student');

                    if (!$j('table').find('tr:nth-child('+rowNumber+')').hasClass('highlight-student')) {
                        $j('table').find('tr:nth-child('+rowNumber+')').addClass('highlight-student');
                    }
                }
            }, function() {
                if (!$j('.inputMark').is(':focus')) {
                    $j('table').find('tr').removeClass('highlight-student');
                }
            });



        },

        cellValidation: function() {

            $j('.mark-input, .comment').blur(function(){
                if ($j(this).val() !== '') {
                    $j(this).closest('form').submit();
                }
            });


            /*
            * Depends on the presence of the jquery.validate library.
            *
            * */

            jQuery.validator.addMethod("percentage", function(value, element) {
                return this.optional(element) || (parseFloat(value) <= 100);
            }, "Please enter a value out of 100");

            jQuery.validator.addMethod("markLimit", function(value, element) {

                var markLimit = parseInt($j(element).siblings('span').children('.validation-limit').text());

                if (value !== "") {
                    if (parseInt(value) <= markLimit) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return true;
                }

            }, "Amount must be less or equal to mark limit");


            jQuery.validator.addClassRules({

                percent: {
                    percentage: true
                },

                'out-of': {
                    markLimit: true
                }

            });

            $j('.markForm').each(function() {
                $j(this).validate({
                    submitHandler: function(form) {
                        var notification = $j(form).siblings('.save-notification');
                        $j.post('/submission/returnWorkForm.php', $j(form).serialize(), function(data) {
                            notification.clearQueue();
                            notification.stop().fadeIn(500).delay(1000).fadeOut(500);
                        });
                        return false;
                    },
                    errorPlacement: function(error,element) {
                        return true;
                    }
                });
            });

        }
    }
    markSheet.init();

});