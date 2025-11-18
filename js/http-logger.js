		(function(){
			function copyToClipboard(text) {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					return navigator.clipboard.writeText(text);
				}
				var ta = document.createElement('textarea');
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.left = '-9999px';
				document.body.appendChild(ta);
				ta.select();
				try {
					document.execCommand('copy');
				} catch(e) {}
				document.body.removeChild(ta);
			}

			function syntaxHighlight(content) {
				try {
					const parsed = JSON.parse(content);
					let jsonStr = JSON.stringify(parsed, null, 4);

					// Escapar entidades HTML antes de agregar spans
					jsonStr = jsonStr
						.replace(/&/g, '&amp;')
						.replace(/</g, '&lt;')
						.replace(/>/g, '&gt;');

					// Primero resalta strings, keys, números, booleanos, null
					jsonStr = jsonStr.replace(
						/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|\b-?\d+(\.\d+)?([eE][+\-]?\d+)?\b)/g,
						function (match) {
							let cls = 'json-number';
							if (/^"/.test(match)) {
								if (/:$/.test(match)) cls = 'json-key';
								else cls = 'json-string';
							} else if (/true|false/.test(match)) cls = 'json-boolean';
							else if (/null/.test(match)) cls = 'json-null';
							return `<span class="${cls}">${match}</span>`;
						}
					);

					// Luego resalta llaves y corchetes (sin romper los spans anteriores)
					jsonStr = jsonStr.replace(
						/([{}\[\]])/g,
						'<span class="json-bracket">$1</span>'
					);

					return jsonStr;
				} catch (e) {
					// HTML fallback highlighting
					content = content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
					content = content.replace(/(&lt;!--[\s\S]*?--&gt;)/gi, '<span class="html-comment">$1</span>');
					content = content.replace(/(&lt;\/?)([a-z0-9\-]+)(\s*[^&]*?)(\/?&gt;)/gi, function(match, open, tagName, attrStr, close) {
						var highlighted = '<span class="html-bracket">'+open+'</span>';
						highlighted += '<span class="html-tag">'+tagName+'</span>';
						if(attrStr){
							attrStr = attrStr.replace(/([a-z\-]+)(\s*=\s*)("[^"]*"|'[^']*')/gi,
								'<span class="html-attr">$1</span>$2<span class="html-value">$3</span>');
							highlighted += attrStr;
						}
						highlighted += '<span class="html-bracket">'+close+'</span>';
						return highlighted;
					});
					return content;
				}
			}


			document.addEventListener('DOMContentLoaded', function() {
				var modal = document.getElementById('ghl-modal');
				var modalContent = document.getElementById('ghl-modal-content');
				var closeBtn = modal.querySelector('.close-ghl');
				var copyBtn = document.getElementById('ghl-copy-btn');

				document.querySelectorAll('.ghl-modal-btn').forEach(function(btn) {
					btn.addEventListener('click', function(){
						var content = btn.dataset.content || '';
						modalContent.innerHTML = syntaxHighlight(content);
						modal.style.display = 'flex';
					});
				});

				closeBtn.addEventListener('click', function() { modal.style.display = 'none'; });
				window.addEventListener('click', function(e) { if(e.target === modal) modal.style.display = 'none'; });

				copyBtn.addEventListener('click', function(){
					var text = modalContent.textContent || '';
					copyToClipboard(text);
					var notice = document.createElement('div');
						notice.textContent = 'Copied to clipboard';
						notice.style.position = 'fixed';
						notice.style.top = '50%';
						notice.style.left = '50%';
						notice.style.transform = 'translate(-50%, -50%)';
						notice.style.background = 'rgba(0, 115, 170, 0.9)';
						notice.style.color = '#fff';
						notice.style.padding = '10px 20px';
						notice.style.borderRadius = '6px';
						notice.style.zIndex = '10003';
						notice.style.fontSize = '16px';
						notice.style.boxShadow = '0 2px 10px rgba(0,0,0,0.3)';
						notice.style.transition = 'opacity 0.3s ease';
						notice.style.opacity = '1';
						document.body.appendChild(notice);

						// fade-out effect
						setTimeout(function() {
							notice.style.opacity = '0';
							setTimeout(function(){ document.body.removeChild(notice); }, 300);
						}, 1200);
				});
			});
		})();
