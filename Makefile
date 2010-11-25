#!/bin/make
# EXPERIMENTAL!

PLUGINS_DIR:=blogs/plugins
TINYMCE_DIR:=$(PLUGINS_DIR)/tinymce_plugin

default:
	@echo "This Makefile is experimental. Therefore it does nothing by default."
	@exit 1

dist: export-source remove-cvs-log

export-source:
	bzr export dist/ || true

# Remove CVS log comment blogs
# EXPERIMENTAL?!
remove-cvs-log:
	find ./dist/ -name \*.php -print0 | xargs -0 -r php -r 'foreach( array_slice($$argv, 1) as $$file ) file_put_contents($$file, preg_replace("~^/\*[^\n]*\n \* \\\$$Log:[^\n]*\\\$$\n.* \*/\n?~sm", "", file_get_contents($$file)));'

#TODO: should depend on directory "dist"
distclean:
	$(RM) -rf dist/

# Create ctags tags file
tags:
	ctags --exclude=blogs/media/* --exclude=.bzr -R

build_tinymce:
	# make sure we're on the expected branch
	test "$(shell git --git-dir $(TINYMCE_DIR)/tinymce.git/.git branch | grep '^* ' | cut -f2 -d' ')" = "evocms_tinymce_plugin"
	test "$(shell git --git-dir $(TINYMCE_DIR)/tinymce_compressor.git/.git branch | grep '^* ' | cut -f2 -d' ')" = "evocms_tinymce_plugin"
	cd $(TINYMCE_DIR) && \
	$(RM) -r tiny_mce && \
		cd tinymce.git && \
			ant release && cd .. && \
		cp -a tinymce.git/tmp/tinymce/jscripts/tiny_mce tiny_mce && \
		cd tinymce_compressor.git && \
	 		ant php && cd .. && \
		cp -a tinymce_compressor.git/tmp/tinymce_compressor_php/tiny_mce_gzip.js tiny_mce && \
		cp -a tinymce_compressor.git/tmp/tinymce_compressor_php/tiny_mce_gzip.php tiny_mce

.PHONY: dist export-source remove-cvs-log distclean tags
