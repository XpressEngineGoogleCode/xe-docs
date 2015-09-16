# Introduction #

There are two types of views in XE Docs
- frontend
- backend

Skinning involves customizing the frontend of the module. This wiki also describes the admin, but this should not be modified.

# Details #

## Fronted views ##

### Index page ###
Default view that is open when no action is specified.

Displays manual tree and current document content.

Action method: `dispXedocsIndex`

### Edit document page ###
Open XE Editor for modifying the content of a document. Also used for creating new documents, when no document is specified in URL.

Action method: `dispXedocsEditPage`

### Document history ###
Displays a log of all edits made to a document (if document history is enabled in Addition settings // TODO Re-add the additional settings tab to backend)

Action method: `dispXedocsHistory`

### Search results ###
Displays a list of search results.

Action method: `dispXedocsSearchResults`

### Document list ###
View for displaying a list of all the documents from a manual.

Action method: `dispXedocsTitleIndex`

### Tree edit page ###
View for changing documents hierarchy (tree)

Action method: `dispXedocsModifyTree`

### Comment pages ###
Used for replying to an existing comment, editing a comment or deleting one.

Action methods:
`dispXedocsReplyComment`
`dispXedocsModifyComment`
`dispXedocsDeleteComment`

Add your content here.  Format your content with:
  * Text in **bold** or _italic_
  * Headings, paragraphs, and lists
  * Automatic links to other wiki pages