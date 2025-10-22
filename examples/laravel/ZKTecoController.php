<?php

namespace App\Http\Controllers;

use App\Services\ZKTecoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Exception;

/**
 * Laravel Controller for ZKTeco Device Management
 * 
 * This controller handles HTTP requests for ZKTeco device operations
 * in a Laravel web application, providing both web and API endpoints.
 */
class ZKTecoController extends Controller
{
    protected $zkTecoService;

    public function __construct(ZKTecoService $zkTecoService)
    {
        $this->zkTecoService = $zkTecoService;
    }

    /**
     * Display ZKTeco dashboard
     * 
     * @return View
     */
    public function index(): View
    {
        try {
            $deviceStatus = $this->zkTecoService->getDeviceStatus();
            return view('zkteco.dashboard', compact('deviceStatus'));
        } catch (Exception $e) {
            return view('zkteco.dashboard', [
                'deviceStatus' => [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Test device connection (API endpoint)
     * 
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $isConnected = $this->zkTecoService->testConnection();
            
            return response()->json([
                'success' => true,
                'connected' => $isConnected,
                'message' => $isConnected ? 'Device connected successfully' : 'Device connection failed'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get device information (API endpoint)
     * 
     * @return JsonResponse
     */
    public function getDeviceInfo(): JsonResponse
    {
        try {
            $deviceInfo = $this->zkTecoService->getDeviceInfo();
            
            return response()->json([
                'success' => true,
                'data' => $deviceInfo
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve device information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract users from device
     * 
     * @return JsonResponse|View
     */
    public function extractUsers(Request $request)
    {
        try {
            $users = $this->zkTecoService->extractUsers();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $users,
                    'count' => count($users)
                ]);
            }
            
            return view('zkteco.users', compact('users'));
            
        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to extract users',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to extract users: ' . $e->getMessage());
        }
    }

    /**
     * Extract attendance records from device
     * 
     * @return JsonResponse|View
     */
    public function extractAttendance(Request $request)
    {
        try {
            $attendance = $this->zkTecoService->extractAttendance();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $attendance,
                    'count' => count($attendance)
                ]);
            }
            
            return view('zkteco.attendance', compact('attendance'));
            
        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to extract attendance records',
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to extract attendance: ' . $e->getMessage());
        }
    }

    /**
     * Sync users to database
     * 
     * @return JsonResponse
     */
    public function syncUsers(): JsonResponse
    {
        try {
            $syncedCount = $this->zkTecoService->syncUsersToDatabase();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$syncedCount} users to database",
                'synced_count' => $syncedCount
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync users to database',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync attendance records to database
     * 
     * @return JsonResponse
     */
    public function syncAttendance(): JsonResponse
    {
        try {
            $syncedCount = $this->zkTecoService->syncAttendanceToDatabase();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$syncedCount} attendance records to database",
                'synced_count' => $syncedCount
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync attendance to database',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete sync operation (users + attendance)
     * 
     * @return JsonResponse
     */
    public function fullSync(): JsonResponse
    {
        try {
            // Sync users first
            $usersSynced = $this->zkTecoService->syncUsersToDatabase();
            
            // Then sync attendance
            $attendanceSynced = $this->zkTecoService->syncAttendanceToDatabase();
            
            return response()->json([
                'success' => true,
                'message' => 'Full synchronization completed successfully',
                'users_synced' => $usersSynced,
                'attendance_synced' => $attendanceSynced
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Full synchronization failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download users as CSV
     * 
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadUsersCSV()
    {
        try {
            $users = $this->zkTecoService->extractUsers();
            
            $filename = 'zkteco_users_' . date('Y-m-d_H-i-s') . '.csv';
            
            return response()->streamDownload(function () use ($users) {
                $handle = fopen('php://output', 'w');
                
                // CSV headers
                fputcsv($handle, ['UID', 'User ID', 'Name', 'Privilege', 'Group ID', 'Card']);
                
                // CSV data
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user['uid'],
                        $user['user_id'],
                        $user['name'],
                        $user['privilege'],
                        $user['group_id'],
                        $user['card']
                    ]);
                }
                
                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv']);
            
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to download users CSV: ' . $e->getMessage());
        }
    }

    /**
     * Download attendance as CSV
     * 
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadAttendanceCSV()
    {
        try {
            $attendance = $this->zkTecoService->extractAttendance();
            
            $filename = 'zkteco_attendance_' . date('Y-m-d_H-i-s') . '.csv';
            
            return response()->streamDownload(function () use ($attendance) {
                $handle = fopen('php://output', 'w');
                
                // CSV headers
                fputcsv($handle, ['UID', 'User ID', 'Timestamp', 'Date', 'Time', 'Status', 'Punch']);
                
                // CSV data
                foreach ($attendance as $record) {
                    fputcsv($handle, [
                        $record['uid'],
                        $record['user_id'],
                        $record['timestamp'],
                        $record['date'],
                        $record['time'],
                        $record['status'],
                        $record['punch']
                    ]);
                }
                
                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv']);
            
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to download attendance CSV: ' . $e->getMessage());
        }
    }
}