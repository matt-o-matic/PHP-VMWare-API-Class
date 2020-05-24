<?php

// saveJSON: XML to JSON conversion/transformation

// The saveJSON method is implemented as a method of the example DOMDocumentExtended class, which
// extends DOMDocument.  It will transform the currently loaded DOM into JSON using XSLT.  Since
// there isn't a reliable automatic way of detecting if certain siblings nodes should be
// represented as arrays or not, a "forceArray" parameter can be passed to the saveJSON method.
// It should be noted that the forceArray functionality doesn't recognize namespaces.  This is
// an easy enough fix, I just didn't need it at the time of writing (look for local-name() and
// modify accordingly).

// It should be quite trivial to port this code to another language since the bulk of the work is
// done using an XSL stylesheet.

class DOMDocumentExtended extends DOMDocument
{
	public function saveJSON( $forceArray = array() )
	{
		$xsltParameters = array( "force_array" => "|" . implode( "|", $forceArray ) . "|" );

		$xslDocument = new DOMDocument();
		$xslDocument->loadXML( XML_TO_JSON_STYLESHEET );

		try
		{
			$processor = new XSLTProcessor();
			$processor->importStyleSheet( $xslDocument );
			$processor->setParameter( "", $xsltParameters );
			$result = $processor->transformToXML( $this );

			$failure = $result === false || empty( $result );
		}
		catch( Exception $e )
		{
			$failure = $result = false;
		}

		if( $failure )
		{
			// TODO: implement error handling (throw an exception, preferably looking up libxml errors)
		}

		return $result;
	}
}

define( "XML_TO_JSON_STYLESHEET", <<<'XML'
<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output
		method="text"
		encoding="UTF-8"
		indent="no"
		omit-xml-declaration="yes"
	/>
	<xsl:param name="force_array" select="''" />
	<xsl:template match="/">
		<xsl:text>{</xsl:text>
			<xsl:apply-templates select="." mode="normal" />
		<xsl:text>}</xsl:text>
	</xsl:template>
	<xsl:template match="*" mode="normal">
		<xsl:choose>
			<xsl:when test="contains( $force_array, concat( '|', local-name(), '|' ) )">
				<xsl:if test="position() = 1">
					<xsl:call-template name="element_as_array" />
				</xsl:if>
			</xsl:when>
			<xsl:when test="not(child::*)">
				<xsl:call-template name="element_as_leaf" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="element_as_object" />
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	<xsl:template name="element_as_object">
		<xsl:text>"</xsl:text><xsl:value-of select="local-name()" /><xsl:text>":{</xsl:text>
			<xsl:call-template name="attributes" />
			<xsl:if test="@*"><xsl:if test="*"><xsl:text>,</xsl:text></xsl:if></xsl:if>
			<xsl:apply-templates select="*" mode="normal" />
		<xsl:text>}</xsl:text>
		<xsl:if test="position() != last()"><xsl:text>,</xsl:text></xsl:if>
	</xsl:template>
	<xsl:template name="element_as_leaf">
		<xsl:text>"</xsl:text><xsl:value-of select="local-name()" /><xsl:text>":"</xsl:text><xsl:call-template name="escape"><xsl:with-param name="string" select="." /></xsl:call-template><xsl:text>"</xsl:text>
		<xsl:if test="position() != last()"><xsl:text>,</xsl:text></xsl:if>
	</xsl:template>
	<xsl:template name="element_as_array">
		<xsl:variable name="element_name" select="name()" />
		<xsl:variable name="elements" select="preceding-sibling::*[name()=$element_name]|self::*|following-sibling::*[name()=$element_name]" />
		<xsl:if test="count($elements)">
			<xsl:text>"</xsl:text><xsl:value-of select="local-name()" /><xsl:text>":[</xsl:text>
				<xsl:for-each select="$elements">
					<xsl:text>{</xsl:text>
						<xsl:call-template name="attributes" />
						<xsl:if test="@*"><xsl:if test="*"><xsl:text>,</xsl:text></xsl:if></xsl:if>
						<xsl:apply-templates select="*" mode="normal" />
					<xsl:text>}</xsl:text>
					<xsl:if test="position() != last()">,</xsl:if>
				</xsl:for-each>
			<xsl:text>]</xsl:text>
		</xsl:if>
	</xsl:template>
	<xsl:template name="attributes">
		<xsl:if test="@*">
			<xsl:text>"@":{</xsl:text>
				<xsl:apply-templates select="@*" />
			<xsl:text>}</xsl:text>
		</xsl:if>
	</xsl:template>
	<xsl:template match="@*">
		<xsl:call-template name="element_as_leaf" />
	</xsl:template>
	<xsl:template name="escape">
		<xsl:param name="string" select="''" />
		<xsl:call-template name="string-replace-all">
			<xsl:with-param name="text">
				<xsl:call-template name="string-replace-all">
					<xsl:with-param name="text">
						<xsl:call-template name="string-replace-all">
							<xsl:with-param name="text">
								<xsl:call-template name="string-replace-all">
									<xsl:with-param name="text">
										<xsl:call-template name="string-replace-all">
											<xsl:with-param name="text" select="$string" />
											<xsl:with-param name="replace" select="'&#x9;'" />
											<xsl:with-param name="by" select="'\t'" />
										</xsl:call-template>
									</xsl:with-param>
									<xsl:with-param name="replace" select="'&#xD;'" />
									<xsl:with-param name="by" select="'\r'" />
								</xsl:call-template>
							</xsl:with-param>
							<xsl:with-param name="replace" select="'&#xA;'" />
							<xsl:with-param name="by" select="'\n'" />
						</xsl:call-template>
					</xsl:with-param>
					<xsl:with-param name="replace" select="'\'" />
					<xsl:with-param name="by" select="'\\'" />
				</xsl:call-template>
			</xsl:with-param>
			<xsl:with-param name="replace" select="'&quot;'" />
			<xsl:with-param name="by" select="'\&quot;'" />
		</xsl:call-template>
	</xsl:template>
	<xsl:template name="string-replace-all">
		<xsl:param name="text" />
		<xsl:param name="replace" />
		<xsl:param name="by" />
		<xsl:choose>
			<xsl:when test="contains($text, $replace)">
				<xsl:value-of select="substring-before($text, $replace)" />
				<xsl:value-of select="$by" />
				<xsl:call-template name="string-replace-all">
					<xsl:with-param name="text" select="substring-after($text, $replace)" />
					<xsl:with-param name="replace" select="$replace" />
					<xsl:with-param name="by" select="$by" />
				</xsl:call-template>
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="$text" />
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
</xsl:stylesheet>
XML
);
?>