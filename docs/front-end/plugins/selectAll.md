# Select All plugin
This plugin implements select-all functionality for checkboxes.

_Note: this plugin should be used for all future select-all needs. However, some places in the system currently use attacher.select-all.js instead. The "attacher" doesn't actually attach any plugins, it just implements the select-all feature itself from scratch. Please don't use it in future._

## Basic usage

#### Markup

```
<div class="checklist">
    <input id="selectAll" type="checkbox">
    <label for="selectAll">Select All</label>
    
    <input id="checkbox1" type="checkbox">
    <label for="checkbox1">
        A Checkbox
    </label>
    
    <input id="checkbox2" type="checkbox">
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

To avoid taking up excessive vertical space, child checkboxes are hidden by default. Selecting the parent checkbox will show the child checkboxes.

Checkboxes that have children have an arrow icon indicating that they can be expanded to see their children.

#### Markup

```
<div class="checklist">
    <input id="selectAll" type="checkbox">
    <label for="selectAll">Select All</label>
    
    <input id="parentCheckbox1" type="checkbox">
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
    checkboxes: "[data-checkbox]"
    childCheckboxes: "[data-child-checkbox]",
    childCheckboxGroups: "[data-child-group]"
});
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