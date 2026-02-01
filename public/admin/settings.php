        <label>API ID</label>
        <input type="text" name="dmm_api_id" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.api_id', ''), ENT_QUOTES, 'UTF-8'); ?>">
        <label>Affiliate ID</label>
        <input type="text" name="dmm_affiliate_id" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.affiliate_id', ''), ENT_QUOTES, 'UTF-8'); ?>">
        <label>Site</label>
        <input type="text" name="dmm_site" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.site', ''), ENT_QUOTES, 'UTF-8'); ?>">
        <label>Service</label>
        <input type="text" name="dmm_service" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.service', ''), ENT_QUOTES, 'UTF-8'); ?>">
        <label>Floor</label>
        <input type="text" name="dmm_floor" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.floor', ''), ENT_QUOTES, 'UTF-8'); ?>">

        <label>Hits</label>
        <input type="number" name="dmm_hits" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.hits', ''), ENT_QUOTES, 'UTF-8'); ?>">
        <label>Sort</label>
        <input type="text" name="dmm_sort" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.sort', ''), ENT_QUOTES, 'UTF-8'); ?>">
        <label>Output</label>
        <input type="text" name="dmm_output" value="<?php echo htmlspecialchars((string)array_get_path($currentConfig, 'dmm_api.output', ''), ENT_QUOTES, 'UTF-8'); ?>">
