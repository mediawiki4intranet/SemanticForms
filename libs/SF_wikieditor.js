// create ext if it does not exist yet
if ( window.ext == null || typeof( window.ext ) === "undefined" ) {
	window.ext = {};
}

window.ext.wikieditor = {
	// initialize the wikieditor on the specified element
	init : function init ( input_id, params ) {
		if ( window.mediaWiki ) {
			mediaWiki.loader.using( mediaWiki.config.get( 'wgWikiEditorResourceModules' ), function() {
				jQuery( '#'+input_id ).wikiEditor();
			});
		}
	}
};
