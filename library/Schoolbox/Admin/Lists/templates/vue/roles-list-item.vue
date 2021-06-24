<script>
export default {
    
    props: {
        record: { //record to display
            type: Object,
            required: true
        },
        isSortable: { //whether drag n drop is enabled
            type: Boolean,
            required: true
        },
    },

    data () {
        return {
            
        }
    },

    created () {
        // debugger;
    },

    mounted () {
        
    },

    computed: {


    },

    methods: {
        

    }

}
</script>

<template>
    <li :data-record-id="1">
        <input type="hidden" name="id[1]" value="1">
        <div class="actions-small-1">
            <div class="grip ui-sortable-handle"></div>
            <div class="list-item">
                <div class="small-12 medium-7 column">
                    <label>
                        <span class="hide-for-medium-up">Name</span>
                        <input name="mandatory-name[1]" type="text" placeholder="Name" value="Administration" maxlength="50" style="background-image: url(&quot;data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABHklEQVQ4EaVTO26DQBD1ohQWaS2lg9JybZ+AK7hNwx2oIoVf4UPQ0Lj1FdKktevIpel8AKNUkDcWMxpgSaIEaTVv3sx7uztiTdu2s/98DywOw3Dued4Who/M2aIx5lZV1aEsy0+qiwHELyi+Ytl0PQ69SxAxkWIA4RMRTdNsKE59juMcuZd6xIAFeZ6fGCdJ8kY4y7KAuTRNGd7jyEBXsdOPE3a0QGPsniOnnYMO67LgSQN9T41F2QGrQRRFCwyzoIF2qyBuKKbcOgPXdVeY9rMWgNsjf9ccYesJhk3f5dYT1HX9gR0LLQR30TnjkUEcx2uIuS4RnI+aj6sJR0AM8AaumPaM/rRehyWhXqbFAA9kh3/8/NvHxAYGAsZ/il8IalkCLBfNVAAAAABJRU5ErkJggg==&quot;); background-repeat: no-repeat; background-attachment: scroll; background-size: 16px 18px; background-position: 98% 50%; cursor: auto;">
                    </label>
                </div>
                <div class="small-12 medium-3 column">
                    <label>
                        <span class="hide-for-medium-up">Role Type</span>
                        <select name="value-type[1]">
                            <option value="guest">Guest</option>
                            <option selected="" value="staff">Staff</option>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                        </select>
                    </label>
                </div>
                <div class="small-12 medium-2 column">
                    <label>
                        <span class="hide-for-medium-up">Allow Password Change</span>
                            <select name="value-canChangePassword[1]">
                                <option selected="" value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                    </label>
                </div>
            </div>
            <nav>
                <a class="icon-overflow no-text" data-dropdown="menu-1" href="#" title="Actions"></a>
            </nav>
        </div>
        <ul id="menu-1" data-dropdown-content="" class="f-dropdown">
            <li><a href="#" class="icon-delete" data-delete="1">Delete row</a></li>
        </ul>
    </li>
<!-- 
{% set recordIndex = recordIndex ?? loop.index %}
{% set idIsNumeric = recordIndex matches '/^\\d+$/' %}
{% set deleteAttr  = deleteAttr ?? 'data-delete' %}

<li data-record-id="{{ key }}">
    <input type="hidden" name="id[{{ recordIndex }}]" value="{{ key }}" />
    <div class="actions-small-{{ deletable ? 1 : 0 }}">
        {% if sortable %}
            <div class="grip ui-sortable-handle"></div>
        {% endif %}
        <div class="list-item">
            {% for field_key, field in tables[table].fields | filter(field => field.hidden is not defined or not field.hidden) %}
                {% set record_value = record[field_key] ?? null %}
                {% set disabledAttr = ((field.nullable ?? false) and record_value is null) ? 'disabled' : '' %}
                <div class="small-12 medium-{{ field.width is not empty ? field.width : 2 }} column">
                    {% if field.readonly ?? false %}
                        {#- Read only field, just display the value -#}
                        <p>{{ record_value }}</p>
                    {% else %}
                        <label>
                            <span class="hide-for-medium-up">{{ field.caption }}</span>
                            {% if field.radio ?? false %}
                                {# Radio field, shared between all rows #}
                                <div class="radiolist">
                                    <input type="radio"
                                           name="radio-{{ field_key }}"
                                           value="{{ recordIndex }}"
                                           id="{{ field_key }}-{{ recordIndex }}"
                                        {% if (record_value == 1) %} checked {% endif %}"
                                    {{ disabledAttr }}
                                    />
                                    <label for="{{ field_key }}-{{ recordIndex }}"></label>
                                </div>
                            {% elseif field.checkbox ?? false %}
                                <div class="checklist">
                                    <input name="checkbox-{{ field_key }}[{{ recordIndex }}]" type="hidden" value="0"/>
                                    <input name="checkbox-{{ field_key }}[{{ recordIndex }}]"
                                           value="1"
                                           id="{{ field_key }}-{{ recordIndex }}"
                                           type="checkbox"
                                           {% if record_value %}checked{% endif %}
                                    />
                                    <label for="{{ field_key }}-{{ recordIndex }}"></label>
                                </div>
                            {% elseif field.lookup is defined and field.lookup is iterable %}
                                {# Lookup select box #}
                                {% if field.lookup | length == 0 %}
                                    <select name="value-{{ field_key }}[{{ recordIndex }}]" disabled>
                                        <option value="" disabled>None Available</option>
                                    </select>
                                {% else %}
                                    <select name="value-{{ field_key }}[{{ recordIndex }}]" {{ disabledAttr }}>
                                        {% if not field.mandatory %}
                                            <option value="">Not Selected</option>
                                        {% endif %}
                                        {% for option in field.lookup %}
                                            {% if record_value == option.key or not (option.deleted ?? false) %}
                                            <option
                                                    {% if record_value == option.key %}selected{% endif %}
                                                    value="{{ option.key }}">
                                                {{ option.value }}{% if option.deleted ?? false %} (deleted){% endif %}
                                            </option>
                                            {% endif %}
                                        {% endfor %}
                                    </select>
                                {% endif %}
                            {% elseif field.colour ?? false %}
                                {# Color picker #}
                                <input name="value-{{field_key}}[{{ recordIndex }}]" type="text" placeholder="{{ field.caption }}" value="{{ record_value }}" data-colour-picker {{ disabledAttr }} />
                            {% elseif field.mandatory ?? false %}
                                {# All other fields are just text boxes #}
                                <input name="mandatory-{{field_key}}[{{ recordIndex }}]" type="text" placeholder="{{ field.caption }}" value="{{ record_value }}" maxlength="{{field.maxLength ?? ''}}" {{ disabledAttr }} />
                            {% else %}
                                {# All other fields are just text boxes #}
                                <input name="value-{{field_key}}[{{ recordIndex }}]" type="text" placeholder="{{ field.caption }}" value="{{ record_value }}" maxlength="{{field.maxLength ?? ''}}" {{ disabledAttr }} />
                            {% endif %}
                            {# All nullable fields get a tickbox #}
                            {% if field.nullable ?? false %}
                                <div class="checklist">
                                    <input name="null-{{ field_key }}[{{ recordIndex }}]"
                                           value="{{ idIsNumeric ? recordIndex - 1 : recordIndex }}"
                                           id="{{ field_key }}-{{ recordIndex }}"
                                           type="checkbox"
                                           {% if record_value is null %}checked{% endif %}
                                           data-nullcheck
                                    />
                                    <label for="{{ field_key }}-{{ recordIndex }}">
                                        {{- field.nullable_label | default('Null') -}}
                                    </label>
                                </div>
                            {% endif %}
                        </label>
                    {% endif %}
                </div>
            {% endfor %}
        </div>
        {% if deletable %}
            <nav>
                <a class="icon-overflow no-text" data-dropdown="menu-{{ key }}" href="#" title="Actions"></a>
            </nav>
        {% endif %}
    </div>
    <ul id="menu-{{ key }}" data-dropdown-content class="f-dropdown">
        <li><a href="#" class="icon-delete" {{ deleteAttr | raw }}="{{ key }}">Delete row</a></li>
    </ul>
</li> 
-->
</template>

<style lang="sass">

</style>