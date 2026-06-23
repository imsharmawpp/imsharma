import { NextRequest, NextResponse } from 'next/server';
import { QuestionnaireData } from '@/types';

// In-memory store for demo (use DB in production)
const leadsStore: Array<{
  id: string;
  data: QuestionnaireData;
  createdAt: string;
  status: string;
}> = [];

export async function POST(request: NextRequest) {
  try {
    const data: QuestionnaireData = await request.json();
    
    // Validate required fields
    if (!data.propertyCategory || !data.propertySubType || !data.name || !data.mobileNumber) {
      return NextResponse.json(
        { success: false, error: 'Missing required fields' },
        { status: 400 }
      );
    }

    if (!data.problemAreas || data.problemAreas.length === 0) {
      return NextResponse.json(
        { success: false, error: 'At least one problem area is required' },
        { status: 400 }
      );
    }

    // Create lead record
    const lead = {
      id: `lead_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      data,
      createdAt: new Date().toISOString(),
      status: 'captured',
    };

    leadsStore.push(lead);

    // TODO: In production, send lead to:
    // 1. Your CRM system (HubSpot, Zoho, Salesforce)
    // 2. Internal notification (email/Slack/WhatsApp)
    // 3. Database for lead tracking
    // 4. Analytics platform
    
    console.log(`[LEAD CAPTURED] ${lead.id}:`, {
      name: data.name,
      mobile: data.mobileNumber,
      property: `${data.propertyCategory} - ${data.propertySubType}`,
      problems: data.problemAreas.join(', '),
    });

    return NextResponse.json({
      success: true,
      leadId: lead.id,
      message: 'Lead captured successfully',
    });
  } catch (error) {
    console.error('Lead capture error:', error);
    return NextResponse.json(
      { success: false, error: 'Failed to capture lead' },
      { status: 500 }
    );
  }
}

export async function GET() {
  // Admin endpoint to view leads (protect in production)
  return NextResponse.json({
    success: true,
    leads: leadsStore,
    total: leadsStore.length,
  });
}
