import { NextRequest, NextResponse } from 'next/server';

// Shared OTP store - in production use Redis/DB
// For demo purposes, accept any 6-digit OTP or use a fixed test OTP
const DEMO_OTP = '123456';

export async function POST(request: NextRequest) {
  try {
    const { mobileNumber, otp } = await request.json();
    
    if (!mobileNumber || !otp) {
      return NextResponse.json(
        { success: false, error: 'Mobile number and OTP are required' },
        { status: 400 }
      );
    }

    // In production: verify against stored OTP from WhatsApp/SMS service
    // For development/demo: accept test OTP
    const isValid = otp === DEMO_OTP || otp.length === 6;
    
    if (!isValid) {
      return NextResponse.json(
        { success: false, error: 'Invalid OTP' },
        { status: 401 }
      );
    }

    // TODO: In production:
    // 1. Check OTP against stored value
    // 2. Check expiry
    // 3. Mark as verified in session/JWT
    // 4. Create/find user account linked to mobile number
    // 5. Set authentication token

    return NextResponse.json({
      success: true,
      message: 'Mobile number verified successfully',
      user: {
        mobileNumber,
        verified: true,
      },
    });
  } catch (error) {
    console.error('OTP verify error:', error);
    return NextResponse.json(
      { success: false, error: 'Verification failed' },
      { status: 500 }
    );
  }
}
