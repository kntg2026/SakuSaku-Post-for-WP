<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>SakuSaku Post Bridge Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields('sakusaku_settings'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="sakusaku_api_key">API Key</label></th>
                <td>
                    <input type="text" id="sakusaku_api_key" name="sakusaku_api_key"
                           value="<?php echo esc_attr(get_option('sakusaku_api_key')); ?>"
                           class="regular-text" autocomplete="off" />
                    <p class="description">Enter the API key provided by your SakuSaku Post dashboard.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sakusaku_service_url">Service URL</label></th>
                <td>
                    <input type="url" id="sakusaku_service_url" name="sakusaku_service_url"
                           value="<?php echo esc_attr(get_option('sakusaku_service_url')); ?>"
                           class="regular-text" placeholder="https://app.sakusakupost.com" />
                    <p class="description">URL of the SakuSaku Post service (auto-configured).</p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <hr />
    <h2>Connection Test</h2>
    <p>
        <button type="button" id="sakusaku-test-btn" class="button button-secondary">Test Connection</button>
        <span id="sakusaku-test-result" style="margin-left: 10px;"></span>
    </p>
    <script>
    document.getElementById('sakusaku-test-btn').addEventListener('click', function() {
        const result = document.getElementById('sakusaku-test-result');
        result.textContent = 'Testing...';
        fetch('<?php echo esc_url(rest_url('sakusaku/v1/ping')); ?>', {
            headers: { 'X-Sakusaku-Api-Key': document.getElementById('sakusaku_api_key').value }
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                result.textContent = 'Connected! WP ' + data.wp;
                result.style.color = 'green';
            } else {
                result.textContent = 'Failed: ' + JSON.stringify(data);
                result.style.color = 'red';
            }
        })
        .catch(e => {
            result.textContent = 'Error: ' + e.message;
            result.style.color = 'red';
        });
    });
    </script>
</div>
