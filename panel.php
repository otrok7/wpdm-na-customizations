<?php 
    $before_text = get_option('wpdm_na_login_before_text');
    $cpage = maybe_unserialize(get_option('__wpdm_cpage'));
    $cpage = !is_array($cpage) ? [ 'template' => 'link-template-default', 'cols' => 2, 'colsphone' => 1, 'colspad' => 1, 'heading' => 1 ] : $cpage;
?>
<style>
 .frm td{
     padding:5px;
     border-bottom: 1px solid #eeeeee;

     font-size:10pt;

 }
 h4{
     color: #336699;
     margin-bottom: 0px;
 }
 em{
     color: #888;
 }
.wp-switch-editor{
    height: 27px !important;
}
 </style>
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">Modular Login Form Properties</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="wpdm_na_login_before_text">Anleitungs Text</label>
                        <textarea rows="4" cols="60" id="wpdm_na_login_before_text"  name="wpdm_na_login_before_text"><?php _e($before_text); ?></textarea><br/>
                        <br/>
                    </div>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading"><?php echo 'Category Pages'; ?></div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4">
                                <label><?php echo __( "Link Template:" , "download-manager" ); ?></label><br/>
                                <?php
                                    echo WPDM()->packageTemplate->dropdown(array('name' => '__wpdm_cpage[template]', 'selected' => $cpage['template'], 'class' => 'form-control wpdm-custom-select' ));
                                ?>
                            </div>
                            <div class="col-md-3">
                                <label><?php echo __( "Items Per Page:" , "download-manager" ); ?></label><br/>
                                <input type="number" class="form-control" name="__wpdm_cpage[items_per_page]" value="<?php echo isset($cpage['items_per_page']) ? $cpage['items_per_page'] : 12; ?>">
                            </div>
                            <div class="col-md-5">
                                <label><?php echo __( "Toolbar:" , "download-manager" ); ?></label>
                                <div class="input-group" style="display: flex">
                                    <label class="form-control" style="margin: 0;"><input type="radio" name="__wpdm_cpage[heading]" value="1" <?php checked($cpage['heading'], 1); ?>> <?php echo __( "Show", "download-manager" ) ?></label>
                                    <label class="form-control" style="margin: 0 0 0 -1px;"><input type="radio" name="__wpdm_cpage[heading]" value="0" <?php checked($cpage['heading'], 0); ?>> <?php echo __( "Hide", "download-manager" ) ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php echo __( "Number of Columns", "download-manager" ) ?>:</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <select class="form-control wpdm-custom-select" name="__wpdm_cpage[cols]">
                                        <option value="1">1 Col</option>
                                        <option value="2" <?php selected(2, $cpage['cols']) ?> >2 Cols</option>
                                        <option value="3" <?php selected(3, $cpage['cols']) ?> >3 Cols</option>
                                        <option value="4" <?php selected(4, $cpage['cols']) ?> >4 Cols</option>
                                        <option value="6" <?php selected(6, $cpage['cols']) ?>>6 Cols</option>
                                        <option value="12" <?php selected(12, $cpage['cols']) ?>>12 Cols</option>
                                    </select><div class="input-group-addon">
                                        <i class="fa fa-laptop"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <select class="form-control wpdm-custom-select" name="__wpdm_cpage[colspad]">
                                                <option value="1">1 Col</option>
                                                <option value="2" <?php selected(2, $cpage['colspad']) ?> >2 Cols</option>
                                                <option value="3" <?php selected(3, $cpage['colspad']) ?> >3 Cols</option>
                                                <option value="4" <?php selected(4, $cpage['colspad']) ?> >4 Cols</option>
                                                <option value="6" <?php selected(6, $cpage['colspad']) ?>>6 Cols</option>
                                                <option value="12" <?php selected(12, $cpage['colspad']) ?>>12 Cols</option>
                                    </select><div class="input-group-addon">
                                        <i class="fa fa-tablet-alt"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <select class="form-control wpdm-custom-select" name="__wpdm_cpage[colsphone]">
                                                <option value="1">1 Col</option>
                                                <option value="2" <?php selected(2, $cpage['colsphone']) ?> >2 Cols</option>
                                                <option value="3" <?php selected(3, $cpage['colsphone']) ?> >3 Cols</option>
                                                <option value="4" <?php selected(4, $cpage['colsphone']) ?> >4 Cols</option>
                                                <option value="6" <?php selected(6, $cpage['colsphone']) ?>>6 Cols</option>
                                                <option value="12" <?php selected(12, $cpage['colsphone']) ?>>12 Cols</option>
                                    </select><div class="input-group-addon">
                                        <i class="fa fa-mobile"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <style>select{min-width: auto !important;}</style>
        </div>
    </div>