import { NextRequest, NextResponse } from 'next/server';
import { RECOMMENDATION_TEXT } from '@/lib/constants';
import { FACING_DIRECTIONS, PROBLEM_AREAS } from '@/lib/constants';

export async function POST(request: NextRequest) {
  try {
    const { questionnaire, facingDirection, overlayImage } = await request.json();
    
    if (!questionnaire || !facingDirection) {
      return NextResponse.json(
        { success: false, error: 'Missing required report data' },
        { status: 400 }
      );
    }

    const directionLabel = FACING_DIRECTIONS.find(d => d.value === facingDirection)?.label || facingDirection;
    const problemLabels = (questionnaire.problemAreas || []).map(
      (a: string) => PROBLEM_AREAS.find(p => p.value === a)?.label || a
    );

    // Generate HTML content for PDF
    // In production, use a proper PDF library like Puppeteer, wkhtmltopdf, or jsPDF server-side
    const htmlContent = generateReportHtml({
      name: questionnaire.name,
      propertyCategory: questionnaire.propertyCategory,
      propertySubType: questionnaire.propertySubType,
      sizeInSqFt: questionnaire.sizeInSqFt,
      directionLabel,
      problemLabels,
      overlayImage,
      date: new Date().toLocaleDateString('en-IN', { 
        day: 'numeric', month: 'long', year: 'numeric' 
      }),
    });

    // Return HTML as downloadable PDF placeholder
    // In production: use Puppeteer to convert HTML to PDF
    return new NextResponse(htmlContent, {
      headers: {
        'Content-Type': 'text/html',
        'Content-Disposition': `attachment; filename="vastu-report-${questionnaire.name.replace(/\s+/g, '-')}.html"`,
      },
    });
  } catch (error) {
    console.error('Report generation error:', error);
    return NextResponse.json(
      { success: false, error: 'Failed to generate report' },
      { status: 500 }
    );
  }
}

interface ReportHtmlParams {
  name: string;
  propertyCategory: string;
  propertySubType: string;
  sizeInSqFt?: number;
  directionLabel: string;
  problemLabels: string[];
  overlayImage: string | null;
  date: string;
}

function generateReportHtml(params: ReportHtmlParams): string {
  const {
    name,
    propertyCategory,
    propertySubType,
    sizeInSqFt,
    directionLabel,
    problemLabels,
    overlayImage,
    date,
  } = params;

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vastu Report - ${name}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Georgia', serif; color: #333; line-height: 1.6; }
    .page { max-width: 800px; margin: 0 auto; padding: 40px; }
    .header { text-align: center; border-bottom: 3px solid #D97706; padding-bottom: 24px; margin-bottom: 32px; }
    .header h1 { color: #D97706; font-size: 28px; margin-bottom: 8px; }
    .header .subtitle { color: #666; font-size: 14px; }
    .header .date { color: #999; font-size: 12px; margin-top: 8px; }
    .section { margin-bottom: 32px; }
    .section h2 { color: #1F2937; font-size: 18px; border-bottom: 1px solid #E5E7EB; padding-bottom: 8px; margin-bottom: 16px; }
    .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .detail-item { padding: 8px 0; }
    .detail-item .label { color: #6B7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .detail-item .value { color: #1F2937; font-size: 14px; font-weight: 600; margin-top: 2px; }
    .overlay-image { width: 100%; max-height: 500px; object-fit: contain; border: 1px solid #E5E7EB; border-radius: 8px; margin: 16px 0; }
    .recommendation { background: linear-gradient(135deg, #FEF3C7, #FDE68A); border: 2px solid #D97706; border-radius: 12px; padding: 24px; margin-top: 40px; }
    .recommendation h2 { color: #92400E; border: none; margin-bottom: 16px; font-size: 20px; }
    .recommendation p { color: #78350F; font-size: 13px; margin-bottom: 12px; }
    .recommendation .cta { background: #D97706; color: white; padding: 12px 24px; border-radius: 8px; display: inline-block; text-decoration: none; font-weight: 600; margin-top: 12px; }
    .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #E5E7EB; color: #9CA3AF; font-size: 11px; }
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <h1>Vastu Analysis Report</h1>
      <div class="subtitle">Shilaavinyaas Vastu Consultancy</div>
      <div class="date">Generated on ${date}</div>
    </div>

    <div class="section">
      <h2>Property Details</h2>
      <div class="details-grid">
        <div class="detail-item">
          <div class="label">Client Name</div>
          <div class="value">${name}</div>
        </div>
        <div class="detail-item">
          <div class="label">Property Type</div>
          <div class="value" style="text-transform: capitalize">${propertyCategory} - ${propertySubType.replace(/_/g, ' ')}</div>
        </div>
        <div class="detail-item">
          <div class="label">Facing Direction</div>
          <div class="value">${directionLabel}</div>
        </div>
        ${sizeInSqFt ? `<div class="detail-item">
          <div class="label">Size</div>
          <div class="value">${sizeInSqFt} Sq Ft</div>
        </div>` : ''}
        <div class="detail-item">
          <div class="label">Problem Areas</div>
          <div class="value">${problemLabels.join(', ')}</div>
        </div>
      </div>
    </div>

    <div class="section">
      <h2>Floor Plan with Vastu Chakra Overlay</h2>
      <p style="color: #6B7280; font-size: 13px; margin-bottom: 12px;">
        The Vastu Chakra has been precisely aligned with your ${directionLabel} facing floor plan for accurate zone mapping.
      </p>
      ${overlayImage ? `<img src="${overlayImage}" class="overlay-image" alt="Vastu Chakra Overlay" />` : '<p style="color: #EF4444; font-size: 13px;">Overlay image not available.</p>'}
      <p style="color: #92400E; font-size: 11px; background: #FEF3C7; padding: 8px 12px; border-radius: 6px; margin-top: 12px;">
        <strong>Note:</strong> The Vastu Chakra is oriented with ${directionLabel} direction at the top, matching your property's facing direction.
      </p>
    </div>

    <div class="section">
      <h2>Zone-wise Analysis</h2>
      <p style="color: #6B7280; font-size: 13px;">
        Based on the Vastu Chakra alignment and your reported problem areas (${problemLabels.join(', ')}), 
        the following zones require attention in your property.
      </p>
      <!-- Detailed analysis would be generated here by the report engine -->
    </div>

    <div class="recommendation">
      <h2>⭐ Recommendation</h2>
      ${RECOMMENDATION_TEXT.split('\n\n').map(p => `<p>${p}</p>`).join('\n      ')}
      <br/>
      <a href="#" class="cta">Book a Personalised Consultation →</a>
    </div>

    <div class="footer">
      <p>&copy; ${new Date().getFullYear()} Shilaavinyaas Vastu Consultancy. All rights reserved.</p>
      <p style="margin-top: 4px;">This report is generated based on the floor plan and information provided. For personalised remedies, book an offline consultation.</p>
    </div>
  </div>
</body>
</html>`;
}
