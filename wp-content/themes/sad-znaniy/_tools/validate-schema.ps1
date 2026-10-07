# Проверка Schema.org (JSON-LD) на сайте «Сад знаний».
#
# Запуск:
#   powershell -ExecutionPolicy Bypass -File validate-schema.ps1
#   powershell -ExecutionPolicy Bypass -File validate-schema.ps1 -Base https://calc-doc.ru
#
# Что делает: берёт все URL из sitemap, проверяет код ответа, парсит JSON-LD
# и структуру разметки (ItemList / FAQPage / BreadcrumbList).
param(
	[string]$Base = 'http://sadznaniy.local',
	[string]$SitemapPath = '/sitemap.xml'
)
$ErrorActionPreference = 'Stop'

$url = $Base.TrimEnd('/') + $SitemapPath
$sitemap = (Invoke-WebRequest -Uri $url -UseBasicParsing).Content
$locs = [regex]::Matches( $sitemap, '<loc>([^<]+)</loc>' ) | ForEach-Object { $_.Groups[1].Value }
if ( $locs.Count -eq 0 ) { Write-Output "Не найдено URL в $url"; exit 1 }

$n200 = 0; $njson = 0; $nnojson = 0; $bad = 0
foreach ( $u in $locs ) {
	$errs = @()
	try { $r = Invoke-WebRequest -Uri $u -UseBasicParsing } catch { Write-Output "HTTP FAIL  $u"; $bad++; continue }
	if ( $r.StatusCode -ne 200 ) { Write-Output ("STATUS " + $r.StatusCode + "  " + $u); $bad++; continue }
	$n200++

	$m = [regex]::Match( $r.Content, '<script type="application/ld\+json">(.*?)</script>', 'Singleline' )
	if ( -not $m.Success ) { $nnojson++; Write-Output "нет JSON-LD  $u"; continue }

	$o = $null
	try { $o = $m.Groups[1].Value | ConvertFrom-Json } catch { $errs += 'JSON не парсится' }
	if ( $o -ne $null ) {
		if ( -not $o.'@context' ) { $errs += 'нет @context' }
		$g = @($o.'@graph')
		$il = @($g | Where-Object { $_.'@type' -eq 'ItemList' })
		if ( $il.Count -eq 1 ) {
			$pos = 1
			foreach ( $it in @($il[0].itemListElement) ) {
				if ( [int]$it.position -ne $pos -or -not $it.name ) { $errs += "ListItem (ждём позицию $pos)"; break }
				$pos++
			}
		}
		$fq = @($g | Where-Object { $_.'@type' -eq 'FAQPage' })
		if ( $fq.Count -eq 1 ) {
			foreach ( $q in @($fq[0].mainEntity) ) {
				if ( $q.'@type' -ne 'Question' -or -not $q.name -or $q.acceptedAnswer.'@type' -ne 'Answer' -or -not $q.acceptedAnswer.text ) { $errs += 'Question/Answer'; break }
				if ( $q.acceptedAnswer.text -match '<' ) { $errs += 'HTML в ответе FAQ'; break }
			}
		}
		$bc = @($g | Where-Object { $_.'@type' -eq 'BreadcrumbList' })
		if ( $bc.Count -eq 1 ) {
			$crumbs = @($bc[0].itemListElement)
			$pos = 1
			foreach ( $cr in $crumbs ) {
				$isLast = ( $pos -eq $crumbs.Count )
				if ( [int]$cr.position -ne $pos -or -not $cr.name ) { $errs += "крошка (ждём позицию $pos)"; break }
				if ( -not $isLast -and -not $cr.item ) { $errs += "крошка $pos без item"; break }
				$pos++
			}
		}
	}

	if ( $errs.Count -gt 0 ) { $bad++; Write-Output ("FAIL  $u :: " + ($errs -join ' | ')) } else { $njson++ }
}
Write-Output "=== URL в sitemap: $($locs.Count) | 200: $n200 | валидный JSON-LD: $njson | без JSON-LD: $nnojson | ошибок: $bad ==="
