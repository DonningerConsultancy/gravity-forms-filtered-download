<div class="wrap">
    <h2><?php echo $this->getPluginDisplayName(); ?></h2>

    <form method="post" action="">
    <?php settings_fields($settingsGroup); ?>
        <table class="form-table"><tbody>
        <?php
        if ($optionMetaData != null) {
            foreach ($optionMetaData as $aOptionKey => $aOptionMeta) {
                $displayText = is_array($aOptionMeta) ? $aOptionMeta[0] : $aOptionMeta;
                ?>
                    <tr valign="top">
                        <th scope="row"><p><label for="<?php echo $aOptionKey ?>"><?php echo $displayText ?></label></p></th>
                        <td>
                        <?php $this->createFormControl($aOptionKey, $aOptionMeta, $this->getOption($aOptionKey)); ?>
                        </td>
                    </tr>
                <?php
            }
        }
        ?>
        </tbody></table>
        <p class="submit">
            <input type="submit" class="button-primary"
                   value="<?php _e('Save Changes', 'gravity-forms-filtered-download') ?>"/>
        </p>
    </form>
</div>
