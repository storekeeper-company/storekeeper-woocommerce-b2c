<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

trait FormElementTrait
{
    final protected function getFormStart(string $method = 'get', string $action = null): string
    {
        $action = esc_attr($action ?? add_query_arg([]));
        $method = esc_attr($method);

        return "<form action='$action' method='$method' class='storekeeper-form'>";
    }

    final protected function getFormEnd(): string
    {
        return '</form>';
    }

    final protected function getRequestHiddenInputs()
    {
        $html = '';

        foreach ($_REQUEST as $name => $value) {
            $value = esc_attr($value);
            $html .= "<input type='hidden' name='$name' value='$value' />";
        }

        return $html;
    }

    final protected function getFormHeader(string $title): string
    {
        $title = esc_html($title);

        return <<<HTML
<div class="storekeeper-form-group">
    <h2 class="storekeeper-form-header">$title</h2>
</div>
HTML;
    }

    final protected function getFormNote(string $note, string $class = ''): string
    {
        $note = esc_html($note);
        $class = esc_attr($class);

        return <<<HTML
<div class="storekeeper-form-group">
    <p class="storekeeper-form-note $class">$note</p>
</div>
HTML;
    }

    final protected function getFormGroup(string $label, string $input): string
    {
        $label = esc_html($label);

        return <<<HTML
<div class="storekeeper-form-group">
    <label class="storekeeper-form-label">$label</label>
    <div class="storekeeper-form-input">$input</div>
</div>
HTML;
    }

    final protected function getFormActionGroup(string $actions): string
    {
        return "<div class='storekeeper-form-action-group'>$actions</div>";
    }

    final protected function getFormInput(
        string $name,
        string $placeholder,
        string $value = '',
        string $class = '',
        string $type = 'text'
    ): string {
        $name = esc_attr($name);
        $placeholder = esc_attr($placeholder);
        $value = esc_attr($value);
        $class = esc_attr($class);
        $type = esc_attr($type);

        return "<input type='$type' class='$class' name='$name' value='$value' placeholder='$placeholder' />";
    }

    final protected function getFormCheckbox(
        string $name,
        bool $value = false,
        string $class = ''
    ): string {
        $name = esc_attr($name);
        $checked = esc_attr($value) ? 'checked' : '';
        $class = esc_attr($class);

        return "<input type='checkbox' class='$class' name='$name' $checked />";
    }

    final protected function getFormHiddenInput(string $name, string $value = ''): string
    {
        $name = esc_attr($name);
        $value = esc_attr($value);

        return "<input type='hidden' name='$name' value='$value'/>";
    }

    final protected function getFormButton(
        string $label,
        string $class = 'button',
        string $name = '',
        string $value = '',
        string $type = 'submit'
    ): string {
        $label = esc_attr($label);
        $class = esc_attr($class);
        $name = esc_attr($name);
        $value = esc_attr($value);
        $type = esc_attr($type);

        return "<button type='$type' class='$class' name='$name' value='$value'>$label</button>";
    }

    final protected function getFormLink(
        string $href,
        string $label,
        string $class = '',
        string $target = '_self'
    ): string {
        $href = esc_attr($href);
        $label = esc_html($label);
        $target = esc_attr($target);

        return "<a href='$href' class='$class' target='$target'>$label</a>";
    }

    final protected function getFormSelect(string $name, array $options, string $value = '', string $class = ''): string
    {
        $name = esc_attr($name);
        $class = esc_attr($class);

        $optionHtml = '';
        foreach ($options as $optionValue => $optionLabel) {
            $selected = $optionValue === $value ? 'selected' : '';
            $optionValue = esc_attr($optionValue);
            $optionLabel = esc_html($optionLabel);
            $optionHtml .= "<option value='$optionValue' $selected>$optionLabel</option>";
        }

        return "<select name='$name' class='$class'>$optionHtml</select>";
    }
}
