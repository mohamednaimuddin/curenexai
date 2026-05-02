<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>CurenexAI XML Sitemap</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="robots" content="noindex,follow"/>
    <style type="text/css">
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; margin: 0; padding: 20px; background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #14b8a6; margin-bottom: 5px; font-size: 28px; }
        .subtitle { color: #7c3aed; font-size: 14px; margin-bottom: 15px; font-weight: 500; }
        p { color: #666; margin-bottom: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-box { background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .stat-number { font-size: 24px; font-weight: bold; color: #14b8a6; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        th { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        tr:hover td { background: #f0fdfa; }
        tr:last-child td { border-bottom: none; }
        a { color: #7c3aed; text-decoration: none; word-break: break-all; }
        a:hover { text-decoration: underline; color: #6d28d9; }
        .priority { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .high { background: #d1fae5; color: #059669; }
        .medium { background: #fef3c7; color: #d97706; }
        .low { background: #fee2e2; color: #dc2626; }
        .images { color: #14b8a6; font-weight: 600; }
        .no-images { color: #9ca3af; }
        .freq { text-transform: capitalize; color: #6b7280; }
        .date { color: #6b7280; font-family: monospace; }
        .footer { margin-top: 25px; padding: 20px; background: white; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .footer-brand { color: #14b8a6; font-weight: 600; }
        .footer-tagline { color: #7c3aed; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>CurenexAI XML Sitemap</h1>
        <p class="subtitle">AI-Powered Homeopathic Healthcare Software</p>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><xsl:value-of select="count(sitemap:urlset/sitemap:url)"/></div>
                <div class="stat-label">Total URLs</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><xsl:value-of select="count(sitemap:urlset/sitemap:url/image:image)"/></div>
                <div class="stat-label">Total Images</div>
            </div>
        </div>
        
        <table>
            <tr>
                <th>URL</th>
                <th>Images</th>
                <th>Priority</th>
                <th>Change Freq</th>
                <th>Last Modified</th>
            </tr>
            <xsl:for-each select="sitemap:urlset/sitemap:url">
                <xsl:sort select="sitemap:priority" order="descending"/>
                <tr>
                    <td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td>
                    <td>
                        <xsl:choose>
                            <xsl:when test="count(image:image) > 0">
                                <span class="images"><xsl:value-of select="count(image:image)"/></span>
                            </xsl:when>
                            <xsl:otherwise>
                                <span class="no-images">-</span>
                            </xsl:otherwise>
                        </xsl:choose>
                    </td>
                    <td>
                        <xsl:choose>
                            <xsl:when test="sitemap:priority >= 0.8">
                                <span class="priority high"><xsl:value-of select="sitemap:priority"/></span>
                            </xsl:when>
                            <xsl:when test="sitemap:priority >= 0.5">
                                <span class="priority medium"><xsl:value-of select="sitemap:priority"/></span>
                            </xsl:when>
                            <xsl:otherwise>
                                <span class="priority low"><xsl:value-of select="sitemap:priority"/></span>
                            </xsl:otherwise>
                        </xsl:choose>
                    </td>
                    <td><span class="freq"><xsl:value-of select="sitemap:changefreq"/></span></td>
                    <td><span class="date"><xsl:value-of select="sitemap:lastmod"/></span></td>
                </tr>
            </xsl:for-each>
        </table>
        
        <div class="footer">
            <div class="footer-brand">CurenexAI</div>
            <div class="footer-tagline">Decode Health, Deliver Cure</div>
            <p style="margin-top:10px;font-size:11px;color:#9ca3af;">https://curenexai.com</p>
        </div>
    </div>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
