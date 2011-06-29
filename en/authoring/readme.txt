<!-- $Revision: 267119 $ -->

------------------------------------------
Directory structure

The directory structure of the published doc directory is:
<language>/
	authoring/	(information about contributing to the manual 
			and translations)
	chapters/ 	(general information about PEAR)
	contributing/	(information about contributing to PEAR)
	core/ 		(docs of the PEAR base classes)
	package/ 	(documention of the PEAR packages)

Organization of the package directory
core/package
	<category>/ 	(contains the packages descriptions of a 
			categorie)
	<category>.xml (contains a overview of the packages
			docs of the categorie)

Organization of the <category> directory
<category>/
	<package>/ 	(contains all package related doc files)
	<package>.xml 	(contains a overview of the package
			related doc 'pages')

Organization of the <package> directory
<package>
	<class>
	constants.xml 	(doc of the package related constants)
	example.xml	(contains a bigger/complex example(s))
	intro.xml	(introduction to a package)
	(the last three files are optional)

Organisation of the <class> directory
<class>
	<function>.xml 	(contains docs for a package function,
			one <function>.xml per function)


Content of <function>.xml
	Synopsis (structure of a function)
	Description (what the function does)
	Parameter (required and optional parameters)
	Returns (what is returned (without PEAR_Error))
	Throws (returned PEAR_Errors)
	Note (additional informations)
	Example (a small function related example, optional)
	See (links to related function or manual entries, optional)

-----------------------------------------
Information about the id-attribute

An id-attribute *must* provide by each of this tags:
<chapter>
<sect>
<refentry> 
<refsect1>

structure notes:

<refentry> for function.xml: package.<category>.<package>.<class>.<function>
<refsect1> for function.xml: package.<category>.<package>.<class>.<function>.<contentpart>

------------------------------------------
Important guidelines and tips

Please make sure to examine
package/categorie/mypackage/myclass/myfunction.xml
It contains several essential pieces of information.
