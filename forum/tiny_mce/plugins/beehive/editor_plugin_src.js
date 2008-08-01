(function() {

    tinymce.PluginManager.requireLangPack('beehive');

    tinymce.create('tinymce.plugins.beehive', {

        init : function (ed, url) {

	    var t = this;
            
            t.editor = ed;

	    ed.addCommand('bh_add_quote', t._add_quote, t);
	    ed.addCommand('bh_add_code', t._add_code, t);
	    ed.addCommand('bh_add_spoiler', t._add_spoiler, t);
	    ed.addCommand('bh_add_no_emots', t._add_no_emots, t);
	    ed.addCommand('bh_open_spell_check', t._open_spell_check, t);

	    ed.addButton('bhquote', {title : 'beehive.quote_desc', cmd : 'bh_add_quote', image : url + '/img/quote.gif'});
	    ed.addButton('bhcode', {title : 'beehive.code_desc', cmd : 'bh_add_code', image : url + '/img/code.gif'});
	    ed.addButton('bhspoiler', {title : 'beehive.spoiler_desc', cmd : 'bh_add_spoiler', image : url + '/img/spoiler.gif'});
	    ed.addButton('bhnoemots', {title : 'beehive.noemots_desc', cmd : 'bh_add_no_emots', image : url + '/img/noemots.gif'});
	    ed.addButton('bhspellcheck', {title : 'beehive.spellcheck_desc', cmd : 'bh_open_spell_check', image : url + '/img/spellcheck.gif'});
	},

	getInfo : function() {	    
	    return {
		longname : 'Beehive Forum TinyMCE 3.x Plugin',
		author : 'Project Beehive Forum',
		authorurl : 'http://www.beehiveforum.net',
		infourl : 'http://www.beehiveforum.net',
		version : tinymce.majorVersion + "." + tinymce.minorVersion
	    };
	},

	_add_quote : function() {
	    var ed = this.editor, p = ed.dom.getPos(ed.dom.getParent(ed.selection.getNode(), '*'));    

	    var quote_container = ed.dom.create('div', { id : 'quote', 'class' : 'quotetext' });    
	    var quote_text = ed.dom.create('div', { 'class' : 'quote' }, ed.selection.getContent());

	    ed.dom.add(quote_container, 'b', {}, ed.getLang('beehive.quote_text'));

	    ed.dom.insertAfter(quote_text, ed.selection.getNode());
	    ed.selection.setNode(quote_container);	    
	},

	_add_code : function() {
	    var ed = this.editor, p = ed.dom.getPos(ed.dom.getParent(ed.selection.getNode(), '*'));	

	    var code_container = ed.dom.create('div', { id : 'code-tinymce', 'class' : 'quotetext' });    
	    var code_text = ed.dom.create('pre', { 'class' : 'code' }, ed.selection.getContent());

	    ed.dom.add(code_container, 'b', {}, ed.getLang('beehive.code_text'));

	    ed.dom.insertAfter(code_text, ed.selection.getNode());
	    ed.selection.setNode(code_container);
	},

	_add_spoiler : function() {
	    var ed = this.editor, p = ed.dom.getPos(ed.dom.getParent(ed.selection.getNode(), '*'));

	    var spoiler_container = ed.dom.create('div', { id : 'spoiler', 'class' : 'quotetext' });    
	    var spoiler_text = ed.dom.create('div', { 'class' : 'spoiler' }, ed.selection.getContent());

	    ed.dom.add(spoiler_container, 'b', {}, ed.getLang('beehive.spoiler_text'));

	    ed.dom.insertAfter(spoiler_text, ed.selection.getNode());
	    ed.selection.setNode(spoiler_container);
	},

	_add_no_emots : function() {
	    var ed = this.editor, p = ed.dom.getPos(ed.dom.getParent(ed.selection.getNode(), '*'));    
	    ed.selection.setNode(ed.dom.create('span', { 'class' : 'noemots' }, ed.selection.getContent()));
	},

	_open_spell_check : function() {
	    var ed = this.editor, p = ed.dom.getPos(ed.dom.getParent(ed.selection.getNode(), '*'));
            if (ed.getContent().length > 0) {
                window.open('dictionary.php?webtag=' + webtag + '&obj_id=' + this.editor.id, 'spellcheck','width=450, height=550, resizable=yes, scrollbars=yes');
            }
	}
    })

    tinymce.PluginManager.add('beehive', tinymce.plugins.beehive);

})();