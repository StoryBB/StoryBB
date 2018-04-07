Feature: Board access
	In order to encourage immersion in the forum
	As an administrator
	I want to restrict who can see which parts of the forum and how

	Background:
		Given the following "users" exist:
			| username | primary group   |
			| Admin    | Administrator   |
			| Test     | Regular Members |
		And the following "characters" exist:
			| character_name | username |
			| Harry Potter   | Test     |
			| Draco Malfoy   | Test     |
		And the following "groups" exist:
			| group_name | group level |
			| Gryffindor | character   |
			| Slytherin  | character   |
		And the following "character group members" exist:
			| character name | group      |
			| Harry Potter   | Gryffindor |
			| Draco Malfoy   | Slytherin  |
		And the following "boards" exist:
			| board name          | board parent | can see                                | cannot see |
			| Common Rooms        | none         | Regular Members, Gryffindor, Slytherin |            |
			| Headmaster's Office | none         | Administrator                          |            |

	Scenario: Checking that basic board access is restricted
		When I log in as "Test"
		And I go to the board index
		Then I should see "Common Rooms"
		And I should not see "Headmaster"
		And I log out
		And I wait for 5 seconds
		And I log in as "Admin"
		And I should see "Common Rooms"
		And I should see "Headmaster"

	Scenario: Checking board access restricted in immersive mode with deny groups
		When the following "boards" exist:
			| board name        | board parent | can see         | cannot see |
			| Gryffindor Tower  | Common Rooms | Regular Members | Slytherin  |
			| Slytherin Dungeon | Common Rooms | Regular Members | Gryffindor |
		And the following settings are set:
			| variable              | value |
			| enable_immersive_mode | on    |
			| deny_boards_access    | 1     |
		And I log in as "Test"
		And I switch character to "Harry Potter"
		And I go to the board index
		Then I should see "Gryffindor Tower"
		And I should not see "Slytherin Dungeon"
		And I switch character to "Draco Malfoy"
		And I go to the board index
		And I should see "Slytherin Dungeon"
		And I should not see "Gryffindor Tower"

	Scenario: Checking board access restricted in immersive mode with allow groups
		When the following "boards" exist:
			| board name        | board parent | can see    | cannot see |
			| Gryffindor Tower  | Common Rooms | Gryffindor |            |
			| Slytherin Dungeon | Common Rooms | Slytherin  |            |
		And the following settings are set:
			| variable              | value |
			| enable_immersive_mode | on    |
		And I log in as "Test"
		And I switch character to "Harry Potter"
		And I go to the board index
		Then I should see "Gryffindor Tower"
		And I should not see "Slytherin Dungeon"
		And I switch character to "Draco Malfoy"
		And I go to the board index
		And I should see "Slytherin Dungeon"
		And I should not see "Gryffindor Tower"

	# In simple mode, access isn't applied on character groups.
	Scenario: Checking board access is not restricted in simple non-immersive mode
		When the following "boards" exist:
			| board name        | board parent | can see         | cannot see |
			| Gryffindor Tower  | Common Rooms | Regular Members | Slytherin  |
			| Slytherin Dungeon | Common Rooms | Regular Members | Gryffindor |
		And the following settings are set:
			| variable              | value  |
			| enable_immersive_mode | off    |
			| non_immersive_mode    | simple |
			| deny_boards_access    | 1      |
		And I log in as "Test"
		And I switch character to "Harry Potter"
		And I go to the board index
		Then I should see "Gryffindor Tower"
		And I should see "Slytherin Dungeon"
		And I switch character to "Draco Malfoy"
		And I go to the board index
		And I should see "Slytherin Dungeon"
		And I should see "Gryffindor Tower"

	# If any character is restricted from seeing a board, they all are.
	Scenario: Checking board access is (correctly) restricted in contextual non-immersive mode
		When the following "boards" exist:
			| board name        | board parent | can see         | cannot see |
			| Gryffindor Tower  | Common Rooms | Regular Members | Slytherin  |
			| Slytherin Dungeon | Common Rooms | Regular Members | Gryffindor |
		And the following settings are set:
			| variable              | value      |
			| enable_immersive_mode | off        |
			| non_immersive_mode    | contextual |
			| deny_boards_access    | 1          |
		And I log in as "Test"
		And I switch character to "Harry Potter"
		And I go to the board index
		Then I should not see "Gryffindor Tower"
		And I should not see "Slytherin Dungeon"
		And I switch character to "Draco Malfoy"
		And I go to the board index
		And I should not see "Slytherin Dungeon"
		And I should not see "Gryffindor Tower"

	# As long as one character can see the board, they all can.
	Scenario: Checking board access is (correctly) not restricted in contextual non-immersive mode
		When the following "boards" exist:
			| board name        | board parent | can see    | cannot see |
			| Gryffindor Tower  | Common Rooms | Slytherin  |            |
			| Slytherin Dungeon | Common Rooms | Gryffindor |            |
		And the following settings are set:
			| variable              | value      |
			| enable_immersive_mode | off        |
			| non_immersive_mode    | contextual |
			| deny_boards_access    | 1          |
		And I log in as "Test"
		And I switch character to "Harry Potter"
		And I go to the board index
		Then I should see "Gryffindor Tower"
		And I should see "Slytherin Dungeon"
		And I switch character to "Draco Malfoy"
		And I go to the board index
		And I should see "Slytherin Dungeon"
		And I should see "Gryffindor Tower"