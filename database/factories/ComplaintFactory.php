<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $boroughs = ['MANHATTAN', 'BROOKLYN', 'QUEENS', 'BRONX', 'STATEN ISLAND'];
        $streets = [
            'Broadway', 'Park Avenue', 'Fifth Avenue', 'Madison Avenue', 'Lexington Avenue',
            'Atlantic Avenue', 'Flatbush Avenue', 'Eastern Parkway', 'Ocean Avenue',
            'Northern Boulevard', 'Queens Boulevard', 'Roosevelt Avenue', 'Main Street',
            'Grand Concourse', 'Jerome Avenue', 'Fordham Road', 'Boston Road',
            'Forest Avenue', 'Richmond Avenue', 'Hylan Boulevard'
        ];
        
        return [
            'complaint_number' => $this->faker->unique()->numerify('########'),
            'complaint_type' => $this->faker->randomElement([
                'Noise - Street/Sidewalk',
                'Water System', 
                'Heat/Hot Water',
                'Street Condition',
                'Illegal Parking',
                'Sanitation Condition',
                'Traffic Signal Condition',
                'Graffiti',
                'Animal Abuse'
            ]),
            'descriptor' => $this->faker->sentence(6),
            'agency' => $this->faker->randomElement(['NYPD', 'DOT', 'DSNY', 'DEP', 'HPD', 'DOHMH']),
            'agency_name' => function (array $attributes) {
                return match($attributes['agency']) {
                    'NYPD' => 'New York City Police Department',
                    'DOT' => 'Department of Transportation', 
                    'DSNY' => 'Department of Sanitation',
                    'DEP' => 'Department of Environmental Protection',
                    'HPD' => 'Housing Preservation and Development',
                    'DOHMH' => 'Department of Health and Mental Hygiene',
                    default => 'Municipal Agency'
                };
            },
            'borough' => $this->faker->randomElement($boroughs),
            'city' => 'NEW YORK',
            'incident_address' => function (array $attributes) use ($streets) {
                $number = $this->faker->numberBetween(1, 9999);
                $street = $this->faker->randomElement($streets);
                return "{$number} {$street}";
            },
            'street_name' => $this->faker->randomElement($streets),
            'incident_zip' => $this->faker->randomElement([
                '10001', '10002', '10003', '10009', '10010', '10011', '10012', '10013', '10014', '10016',
                '11201', '11202', '11203', '11204', '11205', '11206', '11207', '11208', '11209', '11210',
                '11101', '11102', '11103', '11104', '11105', '11106', '11109', '11354', '11355', '11356',
                '10451', '10452', '10453', '10454', '10455', '10456', '10457', '10458', '10459', '10460',
                '10301', '10302', '10303', '10304', '10305', '10306', '10307', '10308', '10309', '10310'
            ]),
            'latitude' => $this->faker->latitude(40.4774, 40.9176), // NYC bounds
            'longitude' => $this->faker->longitude(-74.2591, -73.7004), // NYC bounds
            'status' => $this->faker->randomElement(['Open', 'InProgress', 'Closed']),
            'priority' => $this->faker->randomElement(['Low', 'Medium', 'High']),
            'submitted_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'resolved_at' => function (array $attributes) {
                return $attributes['status'] === 'Closed' 
                    ? $this->faker->dateTimeBetween($attributes['submitted_at'], 'now')
                    : null;
            },
            'due_date' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['submitted_at'], '+7 days');
            },
        ];
    }
}
