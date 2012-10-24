$j(function() {
    var markSheet =  {

        columnCount : $j('.mark-sheet.assessments table').find('tr:first').children('th').size(),
        assessmentCellWidth : '200',

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
            }

            $j('.mark-sheet.assessments table').css('width', markSheet.assessmentCellWidth * markSheet.columnCount);

            $j('.height-control').ellipsis();


            if (!$j('.assessments').find('td').length > 0) {

                $j('.assessments').height($j('.names').height()+92)
            }

        },

        inputs: function() {

            $j(".mark-input").live('focusin', function(evt) {
                var $this = $j(this),
                assignmentClass = $this.closest('td'),
                rowIndex = $this.closest("tr").prevAll("tr").length + 1,
                colIndex = assignmentClass.index() + 1;

                /*
                * Row highlighting
                *
                * */

                $j('table').find('tr').removeClass('highlight-student');

                if (!$j('table').find('tr:nth-child('+rowIndex+')').hasClass('highlight-student')) {
                    $j('table').find('tr:nth-child('+rowIndex+')').addClass('highlight-student');
                }

                /*
                * Comment input showing
                *
                * */

                $j('.active-assessment').each(function() {
                    $j(this).removeClass('active-assessment');
                });

                $j('.assessments tbody tr td:not(:nth-child('+colIndex+'))').find('.comment').stop().animate({width:0}, 200).delay(200).hide();
                $j('.assessments tbody tr td:nth-child('+colIndex+')').addClass('active-assessment').find('.comment').stop().show().animate({width:200});

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
                if (!$j('.mark-input').is(':focus')) {
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
                return this.optional(element)
                    || (parseFloat(value) <= 100)
                    || (value == 'Absent')
                    || (value == 'Not Assessed')
                    || (value == 'Not Submitted');
            }, "Please enter a value out of 100");

            jQuery.validator.addMethod("markLimit", function(value, element) {

                var markLimit = parseInt($j(element).siblings('span').children('.validation-limit').text());

                if (value !== "") {
                    if ((parseInt(value) <= markLimit)
                        || (value == 'Absent')
                        || (value == 'Not Assessed')
                        || (value == 'Not Submitted')) {
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
                        var notification = $j(form).find('input, select');
                        $j.post('/submission/returnWorkForm.php', $j(form).serialize(), function(data) {
                            var avg = $j('#avg-'+data.id+' span');
                            avg.html(data.marks.avg);
                            notification.css({ 'border': '1px solid green' });
                            avg.css({ 'color': 'green' });
                            setTimeout(function() {
                                notification.css({ 'border': '1px solid #ccc' });
                                avg.css({ 'color': '#000' });
                            }, 1000)
                        });
                        return false;
                    }
                });
            });

        }
    }
    markSheet.init();

});