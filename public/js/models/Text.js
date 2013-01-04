/** EXTENDED MODEL **/
Garp.dataTypes.Text.on('init', function(){
	
	this.iconCls = 'icon-text';
	
	// Wysiwyg Editor
	this.Wysiwyg = Ext.extend(Garp.WysiwygAbstract, {
		
		allowedTags: ['a','b','i','br','p','div','ul','ol','li'],
		
		data: {
			description: null,
			name: null
		},
		
		filterHtml: function(){
			var scope = this;
			function walk(nodes){
				Ext.each(nodes, function(el){
					el.normalize();
					if (el.tagName) {
						var tag = el.tagName.toLowerCase();
						if (scope.allowedTags.indexOf(tag) == -1) {
							if (el.childNodes.length > 0) {
								while (el.childNodes.length > 0 && el.parentNode) {
									var child = el.childNodes[el.childNodes.length - 1];
									var clone = child.cloneNode(true);
									el.parentNode.insertBefore(clone, el);
									el.removeChild(child);
									el.parentNode.removeChild(el);
									walk(scope.contentEditableEl.dom.childNodes);
								}
							} else if (el.parentNode) {
								el.parentNode.removeChild(el);
							}
						}
					}
					if (el.childNodes) {
						walk(el.childNodes);
					}
				});
			}
			walk(this.contentEditableEl.dom.childNodes);
		},
		
		getData: function(){
			if (this.contentEditableEl) {
				return {
					description: this.contentEditableEl.dom.innerHTML,
					name: this.data.name || false
				};
			} else {
				return '';
			}
		},
		
		setTitle: function(text){
			this.data.name = text;
			this.titleEl.update(text);
			this.titleEl.setDisplayed( text ? true : false);
		},
		
		showTitleDialog: function(){
			Ext.Msg.prompt(__('Garp'), __('Please specify a title or leave empty to remove title:'), function(btn, text){
				if (btn == 'ok') {
					this.setTitle(text);
				}
			}, this, 80, this.data.name);
		},
		
		getMenuOptions: function(){
			return [{
				group: '',
				text: 'Add / remove title',
				handler: this.showTitleDialog
			}];
		},
		
		initComponent: function(){

			this.html += '<h4 class="contenttitle"></h4>'+'<div class="contenteditable">' +
				 			__('Enter text') +
						'</div>'; 
		
			this.on('afterrender', function(){
				this.addClass('wysiwyg-box');
				if (this.col) {
					this.addClass(this.col);
				}
				this.el.select('.dd-handle, .target').each(function(el){
					el.dom.setAttribute(id, Ext.id());
				});

				this.contentEditableEl = this.el.child('.contenteditable');
				this.contentEditableEl.dom.setAttribute('contenteditable', true);
				this.contentEditableEl.removeAllListeners();
				this.contentEditableEl.on('focus', this.filterHtml, this);
				this.contentEditableEl.on('click', this.filterHtml, this);
				this.contentEditableEl.on('blur', this.filterHtml, this);
				
				this.titleEl = this.el.child('.contenttitle');
				this.titleEl.removeAllListeners();
				this.titleEl.on('click', this.showTitleDialog, this);
				
				if (this.data) {
					this.contentEditableEl.update(this.data.description);
					this.titleEl.update(this.data.name || '');
				}
				this.titleEl.setDisplayed( (this.data && this.data.name) || false);
			}, this);
			
			Garp.dataTypes.Text.Wysiwyg.superclass.initComponent.call(this, arguments);

		}
	});
	
});