# Select All plugin
This plugin implements select-all functionality for checkboxes.

_Note: this plugin should be used for all future select-all needs. However, some places in the system currently use attacher.select-all.js instead. The "attacher" doesn't actually attach any plugins, it just implements the select-all feature itself from scratch. Please don't use it in future._

## Basic usage

#### Markup

```
<div class="checklist">
    <input id="selectAll" type="checkbox">
    <label for="selectAll">Select All</label>
    
    <input id="checkbox1" type="checkbox" data-checkbox>
    <label for="checkbox1">
        A Checkbox
    </label>
    
    <input id="checkbox2" type="checkbox" data-checkbox>
    <label for="checkbox2">
        Another Checkbox
    </label>
</div>
```

#### jQuery
The plugin is initialised on the form element that contains the checkboxes.

It has two mandatory options, both jQuery selector strings:
- `selector`, which is the Select All checkbox (it's called _selector_ because it selects other checkboxes, NOT because it's a jQuery selector!)
- `checkboxes`, which is a data attribute that will be present on all checkboxes you want selected.

```
$("#form").selectAll({    
    selector: "#selectAll",
    checkboxes: "[data-checkbox]"
});
```

## Advanced usage

### Child checkboxes
The plugin supports one level of checkbox nesting.

#### Markup
Parent checkboxes get an icon indicating that they have children. This is a useful visual affordance if child checkbox collapsing is implemented (see the jQuery section below).
```
<div class="checklist">
    <input id="selectAll" type="checkbox">
    <label for="selectAll">Select All</label>
    
    <input id="parentCheckbox1" type="checkbox" data-checkbox>
    <label for="parentCheckbox1" title="Select to reveal children">
        Parent Checkbox
        <i class="icon-click-view size-8"></i>
    </label>
    
    <div class="checklist" data-child-group>
        <input id="childCheckbox1" type="checkbox" data-child-checkbox>
        <label for="childCheckbox1">A Child Checkbox</label>
        
        <input id="childCheckbox2" type="checkbox" data-child-checkbox>
        <label for="childCheckbox2">Another Child Checkbox</label>
    </div>
</div>
```

#### jQuery

In addition to the mandatory options described in Basic Usage, child checkbox usage accepts two options:
- `childCheckboxes` - this is a data attribute that will be present on all child checkboxes you want selected
- `childCheckboxGroups` - this is a data attribute that will be present on all child checkbox container divs

```
$("#form").selectAll({    
    selector: "#selectAll",
    checkboxes: "[data-checkbox]",
    childCheckboxes: "[data-child-checkbox]",
    childCheckboxGroups: "[data-child-group]"
});
```

To avoid taking up excessive vertical space, child checkboxes can be hidden by default, and shown only when the parent checkbox is selected.
This functionality should _probably_ be in its own plugin (as it has nothing to do with Select All), but for now, you can use the following jQuery:

```
// For simplicity, I've used the data attributes on their own here, but this is not recommended for performance reasons.
// In your own code, please make these 3 selectors more specific by including parent elements such as input or form.
var parentCheckboxSelector = "[data-checkbox]";
var childCheckboxSelector = "[data-child-checkbox]";
var childGroupSelector = "[data-child-group]";

// Initial setup: if any parent types are already checked (from saved data), show their subtypes
// otherwise hide the subtypes to save space
$(parentCheckboxSelector).each(function(index, parentCheckbox) {
    if ($(parentCheckbox).is(':checked')) {
        // ...show the subtypes for this type
        $(parentCheckbox).siblings(childGroupSelector).show();
    } else {
        // Hide subtypes by default to reduce vertical scrolling
        $(parentCheckbox).siblings(childGroupSelector).hide();
    }
});

// When a parent type checkbox is changed, show or hide its subtypes
// and check them by default when shown
$(parentCheckboxSelector).change(function(e) {
    var parentCheckbox = e.target;
    var $subCheckboxes = $(parentCheckbox).siblings(childGroupSelector).find(childCheckboxSelector);

    if ($(parentCheckbox).is(':checked')) {
        // ...show the subtypes for this type
        $(parentCheckbox).siblings(childGroupSelector).show();

        // ...and check them by default
        $subCheckboxes.each(function(index, subCheckbox) {
            $(subCheckbox).prop('checked', true);
        });

        $(parentCheckbox).siblings("label").find("i").removeClass("icon-click-view").addClass("icon-dropdown");
    } else {
        // ...hide the subtypes for this type
        $subCheckboxes.each(function(index, subCheckbox) {
            $(subCheckbox).prop('checked', false);
            $(parentCheckbox).siblings(childGroupSelector).hide();
        });

        // ...and uncheck them (causes all sorts of problems with select all otherwise)
        $subCheckboxes.each(function(index, subCheckbox) {
            $(subCheckbox).prop('checked', false);
        });

        $(parentCheckbox).siblings("label").find("i").removeClass("icon-dropdown").addClass("icon-click-view");
    }

});

// When a subtype checkbox is changed, find out if any subtypes in that group are still checked
// and if not, uncheck the parent and hide the subtypes
$(parentCheckboxSelector).each(function(index, parentCheckbox) {
    var $subtypes = $(parentCheckbox).siblings(childGroupSelector).find(childCheckboxSelector);

    $subtypes.change(function() {
        if ($subtypes.filter(":checked").length < 1) {
            $(parentCheckbox).prop("checked", false);
            $subtypes.parent(childGroupSelector).hide();
            $(parentCheckbox).siblings("label").find("i").removeClass("icon-dropdown").addClass("icon-click-view");
        } else {
            $(parentCheckbox).prop("checked", true);
            $subtypes.parent(childGroupSelector).show();
            $(parentCheckbox).siblings("label").find("i").removeClass("icon-click-view").addClass("icon-dropdown");
        }
    });
})
```
 
### Custom onChange function
You can supply your own function which is run when the checkbox specified by `selector` is checked or unchecked. 
```
$("#form").selectAll({    
    selector: "#selectAll",
    checkboxes: "[data-checkbox]",
    change: function() {
        alert("Do some stuff!");
    }
});
```

Trying to use `change` to modify the provided selection behavior may have...unexpected results. You have been warned!