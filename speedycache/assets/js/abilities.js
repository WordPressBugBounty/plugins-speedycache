/*
* SpeedyCache - Abilities / MCP
* Client-side interactions for the AI Abilities settings page.
*/
jQuery(document).ready(function($){

	if(typeof speedycache_abilities === 'undefined'){
		return;
	}

	var CFG = window.speedycache_abilities;
	var $doc = $(document);

	// =====================================================================
	// State
	// =====================================================================

	var state = {
		password: null,           // freshly generated application password (memory only)
		username: '',
		activeClient: 'claude-desktop'
	};

	// =====================================================================
	// Helpers
	// =====================================================================

	window.toast = function toast(message, type){
		type = type || 'success';

		var el = $('<div />')
			.addClass('speedycache-toast')
			.addClass(type)
			.html('<span class="dashicons dashicons-yes"></span> ' + message);

		$('body').append(el);
		el.fadeIn(300).delay(3000).fadeOut(300, function(){
			$(this).remove();
		});
	}

	function setMsg($area, message, isError){
		if(!$area || !$area.length){
			return;
		}
		$area.removeClass('is-error is-success');
		if(message){
			$area
				.attr('hidden', false)
				.addClass(isError ? 'is-error' : 'is-success')
				.html(message);
		}else{
			$area.attr('hidden', true).empty();
		}
	}

	function basicAuthHeader(){
		try{
			return 'Basic ' + window.btoa(state.username + ':' + state.password);
		}catch(e){
			return 'Basic <base64-of-username:application-password>';
		}
	}

	// =====================================================================
	// Step 2 - MCP Adapter install / activate
	// =====================================================================

	$doc.on('click', '.speedycache-abilities-adapter-btn', function(){
		var $btn = $(this);
		var action = $btn.data('action');
		var $area = $('.speedycache-abilities-feedback[data-area="adapter"]');

		if($btn.prop('disabled')){
			return;
		}

		$btn.prop('disabled', true);

		var original = $btn.html();
		var busyLabel = CFG.i18n.installing;
		
		if(action === 'activate'){
			busyLabel = CFG.i18n.activating;
		}else if(action === 'update'){
			busyLabel = CFG.i18n.updating;
		}

		$btn.html(busyLabel);
		setMsg($area, '');

		$.ajax({
			url: CFG.ajax_url,
			type: 'POST',
			data: {
				action: 'speedycache_install_mcp_adapter',
				adapter_action: action,
				nonce: CFG.nonce
			},
			success: function(response){
				if(response.success){
					setMsg($area, response.data.message, false);
					toast(response.data.message);
					// Reload to refresh the whole setup status (abilities, adapter state).
					setTimeout(function(){ window.location.reload(); }, 1200);
				}else{
					var msg = (response.data && (response.data.message || response.data)) || 'Install failed.';
					setMsg($area, msg, true);
					$btn.prop('disabled', false).html(original);
				}
			},
			error: function(){
				setMsg($area, 'Unable to contact the server. Please try again.', true);
				$btn.prop('disabled', false).html(original);
			}
		});
	});

	// =====================================================================
	// Step 3 - Application Password generation
	// =====================================================================

	$doc.on('click', '.speedycache-abilities-gen-pass-btn', function(){
		var $btn = $('.speedycache-abilities-gen-pass-btn');
		var $area = $('.speedycache-abilities-feedback[data-area="app-pass"]');

		if($btn.prop('disabled')){
			return;
		}

		$btn.prop('disabled', true);
		var original = $btn.html();
		$btn.html(CFG.i18n.generating);
		setMsg($area, '');

		$.ajax({
			url: CFG.ajax_url,
			type: 'POST',
			data: {
				action: 'speedycache_generate_app_password',
				nonce: CFG.nonce
			},
			success: function(response){
				if(response.success && response.data.password){
					state.password = response.data.password;
					state.username = response.data.username || state.username;

					$('.speedycache-abilities-app-pass-fields')
						.attr('data-hidden', 'false')
						.show();

					$('.speedycache-abilities-username').val(state.username);
					$('.speedycache-abilities-password').val(state.password);

					setMsg($area, response.data.message, false);
					toast(response.data.message);

					renderClientSnippet();
					$btn.prop('disabled', false).html(original);
				}else{
					var msg = (response.data && (response.data.message || response.data)) || CFG.i18n.genErrTitle;
					setMsg($area, msg, true);
					$btn.prop('disabled', false).html(original);
				}
			},
			error: function(){
				setMsg($area, 'Unable to contact the server. Please try again.', true);
				$btn.prop('disabled', false).html(original);
			}
		});
	});

	// =====================================================================
	// Step 4 - Test connection (client-side first; server fallback)
	// =====================================================================

	$doc.on('click', '.speedycache-abilities-test-btn', function(){
		var $btn = $('.speedycache-abilities-test-btn');
		var $result = $('.speedycache-abilities-test-result');
		var $pill = $('.speedycache-abilities-test-pill');

		if($btn.prop('disabled')){
			return;
		}

		if(!state.password){
			$result.attr('data-hidden', 'false').show()
				.removeClass('speedycache-abilities-test-result-ok speedycache-abilities-test-result-error')
				.addClass('speedycache-abilities-test-result-error')
				.html('Please generate an Application Password first.');
			return;
		}

		$btn.prop('disabled', true);
		var original = $btn.html();
		$btn.html(CFG.i18n.testing);
		$pill.removeClass('speedycache-abilities-pill-success speedycache-abilities-pill-error speedycache-abilities-pill-warning speedycache-abilities-pill-neutral').addClass('speedycache-abilities-pill-warning').text('Testing...');
		$result.attr('data-hidden', 'false').show().empty();

		var endpoint = CFG.endpoint_url;
		var start = Date.now();

		// Try a direct browser fetch with Basic auth. If CORS blocks it,
		// fall back to the server-side tester.
		$.ajax({
			url: endpoint,
			type: 'GET',
			headers: { 'Authorization': basicAuthHeader() },
			timeout: 15000,
			dataType: 'json'
		}).done(function(data, textStatus, jqXHR){
			var elapsed = Date.now() - start;
			if(jqXHR.status >= 200 && jqXHR.status < 300 && Array.isArray(data)){
				var count = 0;
				$.each(data, function(_, ability){
					var name = (ability && (ability.name || ability.id)) || '';
					if(typeof name === 'string' && name.indexOf('speedycache-') === 0){
						count++;
					}
				});
				showTestResult(true, 'Authenticated with your Application Password and discovered ' + count + ' SpeedyCache abilities in ' + elapsed + 'ms. Your site is ready to connect an AI client below.');
			}else{
				showTestResult(false, 'The abilities endpoint responded but the data was unexpected. Check the MCP Adapter is active and try again.');
			}
		}).fail(function(jqXHR){
			// Likely CORS - fall back to server-side check.
			serverTestConnection();
		}).always(function(){
			$btn.prop('disabled', false).html(original);
		});

		function showTestResult(ok, message){
			$result.attr('data-hidden', 'false').show()
				.removeClass('speedycache-abilities-test-result-ok speedycache-abilities-test-result-error')
				.addClass(ok ? 'speedycache-abilities-test-result-ok' : 'speedycache-abilities-test-result-error')
				.html((ok ? CFG.i18n.testOkPrefix + ' ' : CFG.i18n.testFailPrefix + ' ') + message);
			$pill.removeClass('speedycache-abilities-pill-success speedycache-abilities-pill-error speedycache-abilities-pill-neutral')
				.addClass(ok ? 'speedycache-abilities-pill-success' : 'speedycache-abilities-pill-error')
				.text(ok ? 'Verified' : 'Failed');

			// Toggle the row's "done" marker so the progress bar reflects the result.
			$btn.closest('.speedycache-abilities-step').toggleClass('speedycache-abilities-step-done', !!ok);
			$btn.closest('.speedycache-abilities-step').toggleClass('speedycache-abilities-step-progress', !ok);

			if(ok){
				toast(message);
			}

			// Persist the result server-side so the pill keeps its state on reload.
			persistTestStatus(ok, message);
		}

		function persistTestStatus(ok, message){
			$.ajax({
				url: CFG.ajax_url,
				type: 'POST',
				data: {
					action: 'speedycache_save_test_status',
					ok: ok ? 1 : 0,
					message: message || '',
					nonce: CFG.nonce
				}
			});
		}

		function serverTestConnection(){
			$.ajax({
				url: CFG.ajax_url,
				type: 'POST',
				data: {
					action: 'speedycache_test_mcp_connection',
					nonce: CFG.nonce,
					username : state.username,
					password : state.password
				},
				success: function(response){
					if(response.success){
						showTestResult(true, response.data.message);
					}else{
						var msg = (response.data && (response.data.message || response.data)) || 'Connection test failed.';
						showTestResult(false, msg);
					}
				},
				error: function(){
					showTestResult(false, 'Could not reach the abilities endpoint. Check the site is reachable and try again.');
				}
			});
		}
	});

	// =====================================================================
	// AI Client Configuration - snippet builder
	// =====================================================================

	var CLIENTS = {
		'claude-desktop': {
			pathLabel: 'Save to:',
			pathValue: 'macOS: ~/Library/Application Support/Claude/claude_desktop_config.json\nWindows: %APPDATA%\\Claude\\claude_desktop_config.json',
			instructions: [
				'Open the file for your OS shown above (create it if it doesn\'t exist). On Linux, use the Claude Code CLI tab instead.',
				'Paste the snippet above into the file.',
				'Restart Claude Desktop to load the SpeedyCache abilities.'
			]
		},
		'claude-code': {
			pathLabel: 'Run in:',
			pathValue: 'Terminal (Claude Code CLI)',
			instructions: [
				'Open a terminal in your project directory.',
				'Paste the command above and press enter.',
				'Exit Claude Code (/exit) and start a new session to load the SpeedyCache abilities.'
			]
		},
		'cursor': {
			pathLabel: 'Add in:',
			pathValue: 'Cursor Settings → MCP → Add Server',
			instructions: [
				'In Cursor, go to Settings → MCP → Add Server.',
				'Paste the snippet above as a new server.',
				'Enable the server in the MCP list to load the SpeedyCache abilities.'
			]
		},
		'vscode': {
			pathLabel: 'Save to:',
			pathValue: '.vscode/mcp.json',
			instructions: [
				'Create a .vscode/mcp.json file in your project root.',
				'Paste the snippet above into the file.',
				'Reload the VS Code window to discover the SpeedyCache MCP server.'
			]
		},
		'antigravity': {
			pathLabel: 'Save to:',
			pathValue: '~/.gemini/config/mcp_config.json',
			instructions: [
				'Open ~/.gemini/config/mcp_config.json (create it if it doesn\'t exist).',
				'Paste the snippet above into the file.',
				'Restart Antigravity CLI to load the SpeedyCache abilities.'
			]
		},
		'opencode': {
			pathLabel: 'Save to:',
			pathValue: 'opencode.json (project root) or ~/.config/opencode/opencode.json',
			instructions: [
				'Open opencode.json in your project root (or ~/.config/opencode/opencode.json for global use). Create it if it doesn\'t exist.',
				'Paste the snippet above into the file.',
				'Start or restart OpenCode to load the Speedycache abilities.'
			]
		},
		'gemini-cli': {
			pathLabel: 'Save to:',
			pathValue: '~/.gemini/settings.json',
			instructions: [
				'Open ~/.gemini/settings.json (create it if it doesn\'t exist).',
				'Paste the snippet above into the file.',
				'Restart Gemini CLI to load the SpeedyCache abilities.'
			]
		}
	};

	function buildSnippet(clientId){
		var data = window.speedycacheAbilitiesData || {};
		var siteUrl = data.siteUrl || '';
		var mcpServerUrl = data.mcpServerUrl || (siteUrl + 'wp-json/mcp/mcp-adapter-default-server');
		var serverName = data.serverName || 'speedycache-site';
		var username = state.username || data.username || data.userPlaceholder || '<your-username>';
		var password = state.password || data.passwordHolder || '<your-application-password>';

		// stdio config used by Claude Desktop / Cursor / Gemini CLI.
		var stdioConfig = {
			command: 'npx',
			args: ['-y', '@automattic/mcp-wordpress-remote'],
			env: {
				WP_API_URL: siteUrl + 'wp-json/mcp/mcp-adapter-default-server',
				WP_API_USERNAME: username,
				WP_API_PASSWORD: password
			}
		};

		var basic;
		try{
			basic = window.btoa(username + ':' + password);
		}catch(e){
			basic = '<base64-of-username:application-password>';
		}

		switch(clientId){
			case 'claude-code':
				return 'claude mcp add ' + serverName + ' \\\n' +
					'  --transport http "' + mcpServerUrl + '" \\\n' +
					'  --header "Authorization: Basic ' + basic + '"';

			case 'vscode': {
				var obj = {
					servers: {}
				};
				obj.servers[serverName] = {
					type: 'http',
					url: mcpServerUrl,
					headers: { 'Authorization': 'Basic ' + basic }
				};
				return JSON.stringify(obj, null, 2);
			}

			case 'cursor': {
				var cur = { name: 'SpeedyCache Site' };
				$.extend(cur, stdioConfig);
				return JSON.stringify(cur, null, 2);
			}

			case 'antigravity': {
				var agy = {
					mcpServers: {}
				};
				agy.mcpServers[serverName] = {
					serverUrl: mcpServerUrl,
					headers: { 'Authorization': 'Basic ' + basic }
				};
				return JSON.stringify(agy, null, 2);
			}

			case 'opencode': {
				var oc = { mcp: {} };
				oc.mcp[serverName] = {
					type: 'remote',
					url: mcpServerUrl,
					headers: { 'Authorization': 'Basic ' + basic }
				};
				return JSON.stringify(oc, null, 2);
			}

			case 'gemini-cli': {
				var gem = { mcpServers: {} };
				gem.mcpServers[serverName] = stdioConfig;
				return JSON.stringify(gem, null, 2);
			}

			case 'claude-desktop':
			default: {
				var desk = { mcpServers: {} };
				desk.mcpServers[serverName] = stdioConfig;
				return JSON.stringify(desk, null, 2);
			}
		}
	}

	function renderClientSnippet(){
		var clientId = state.activeClient;
		var meta = CLIENTS[clientId] || CLIENTS['claude-desktop'];
		var snippet = buildSnippet(clientId);

		$('.speedycache-abilities-client-path-label').text(meta.pathLabel);
		$('.speedycache-abilities-client-path-value').text(meta.pathValue);

		var $snippet = $('.speedycache-abilities-snippet');
		$snippet.text(snippet);

		var $instructions = $('.speedycache-abilities-instructions').empty();
		if(meta.unsupported){
			$snippet.addClass('unsupported');
			$('.speedycache-abilities-copy-btn').prop('disabled', true);
		}else{
			$snippet.removeClass('unsupported');
			$('.speedycache-abilities-copy-btn').prop('disabled', false);
		}

		$.each(meta.instructions, function(i, line){
			$instructions.append('<li>' + line + '</li>');
		});
	}

	$doc.on('click', '.speedycache-abilities-segmented-btn', function(){
		var $tab = $(this);
		$('.speedycache-abilities-segmented-btn').removeClass('active').attr('aria-selected', 'false');
		$tab.addClass('active').attr('aria-selected', 'true');
		state.activeClient = $tab.data('client');
		renderClientSnippet();
	});

	// Row accordion toggling
	$doc.on('click', '.speedycache-abilities-step-toggle', function(){
		var $btn = $(this);
		var $body = $btn.next('.speedycache-abilities-step-body');
		var expanded = $btn.attr('aria-expanded') === 'true';
		$btn.attr('aria-expanded', expanded ? 'false' : 'true');
		if(expanded){
			$body.hide();
		}else{
			$body.show();
		}
	});

	$doc.on('click', '.speedycache-abilities-copy-btn', function(){
		var $btn = $(this);
		var text = $('.speedycache-abilities-snippet').text();

		if(!text){
			return;
		}

		var done = function(){
			var $label = $btn.find('.speedycache-abilities-copy-btn-label');
			var original = $label.text();
			$btn.addClass('is-copied');
			$label.text('Copied!');
			setTimeout(function(){
				$btn.removeClass('is-copied');
				$label.text(original);
			}, 1600);
		};

		if(navigator.clipboard && navigator.clipboard.writeText){
			navigator.clipboard.writeText(text).then(done, function(){
				legacyCopy(text); done();
			});
		}else{
			legacyCopy(text); done();
		}
	});

	function legacyCopy(text){
		var $ta = $('<textarea />').val(text).appendTo('body').select();
		try{ document.execCommand('copy'); }catch(e){}
		$ta.remove();
	}

	// =====================================================================
	// Boot
	// =====================================================================

	$(function(){
		// Pull server-injected config from the <script type="application/json"> tag.
		var $dataTag = $('#speedycache-abilities-data');
		if($dataTag.length){
			try{
				window.speedycacheAbilitiesData = JSON.parse($dataTag.text());
				var d = window.speedycacheAbilitiesData;
				if(d.username){
					state.username = d.username;
				}
			}catch(e){
				window.speedycacheAbilitiesData = {};
			}
		}

		// Pre-fill i18n strings if the localized object didn't provide them.
		if(!CFG.i18n){
			CFG.i18n = {};
		}
		var defaults = {
			installing:  'Installing...',
			activating:  'Activating...',
			generating:  'Generating...',
			testing:     'Testing...',
			genErrTitle: 'Could not generate the password.',
			testOkPrefix:'Success:',
			testFailPrefix: 'Failed:'
		};
		$.each(defaults, function(k, v){
			if(typeof CFG.i18n[k] === 'undefined'){
				CFG.i18n[k] = v;
			}
		});

		renderClientSnippet();
	});

	$('.speedycache_ai_abilities').on('change', function () {

		var checkbox = $(this);
		var isChecked = checkbox.is(':checked') ? 1 : 0;
		
		$.ajax({
			url: CFG.ajax_url,
			type: 'POST',
			data: {
				action: 'speedycache_save_ai_abilities',
				enabled: isChecked,
				nonce: checkbox.data('nonce')
			},
			success: function (res) {
				if(res.success){
					toast(res.data.message);
				}else{
					toast(res.data.message);
					checkbox.prop('checked', !isChecked);
				}

				checkbox.prop('disabled', false);
			},
			error: function (xhr){
				console.log(xhr.responseText);
				toast('Server Error');
				checkbox.prop('checked', !isChecked);
				checkbox.prop('disabled', false);
			}
		});
	});

});